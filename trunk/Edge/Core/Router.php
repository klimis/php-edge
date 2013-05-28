<?php
namespace Edge\Core;

class Router{
	protected $class;
	protected $method;
	protected $args = array();
	protected $oReflection;
	protected $oReflectionMethod;
	protected $instance;
	protected $id;
    protected $response;
    protected $routes;

	const RPC_MATCH = '/jsonrpc.+[\'"]method[\'"]\s*:\s*[\'"](.*?)[\'"]/';

	public function __construct(array $routes){
        $this->routes = $routes;
        $this->response = new Http\Response();
		try{
			$this->setAttrs();
		}catch(Exception $e){
			Logger::log($e->getMessage());
            print $e->getMessage();
			$response = Response::getInstance();
			$response->httpCode = 500;
			$response->write();
		}
	}

	public function getArgs(){
		return $this->args;
	}

	public function getMethod(){
		return $this->method;
	}

	protected function handleServerError(){
        $edge = Edge::app();
		$arg = strtolower($_SERVER['REQUEST_METHOD']);
		$class = new $edge->serverError[0]();
		$this->response->httpCode = 500;
		$this->response->body = call_user_func(array($class, $edge->serverError[1]), $arg);
	}

	protected function handle404Error(){
        $edge = Edge::app();
        $arg = strtolower($_SERVER['REQUEST_METHOD']);
        $class = new $edge->notFound[0]();
        $this->response->httpCode = 404;
        $this->response->body = call_user_func(array($class, $edge->notFound[1]), $arg);
	}

    private static function uriResolver($url, $routes){
        if(isset($routes[$url])){
            $ret = $routes[$url];
            $ret[] = array();
            return $ret;
        }

        $urlDashes = substr_count($url, "/");
        foreach($routes as $requestedUrl => $attrs){
            if(substr_count($requestedUrl, "/") != $urlDashes){
                continue;
            }
            $parts = explode(":", $requestedUrl);

            if(count($parts) > 1){
                $urlToMatch = substr($parts[0], 0, -1);
                if(strncmp ($url, $urlToMatch, strlen($urlToMatch)) === 0){
                    $args = explode("/", substr($url, strlen($urlToMatch), strlen($url)));
                    unset($args[0]);
                    $attrs[] = array_map('htmlspecialchars', $args);
                    return $attrs;
                }
            }
        }
        return false;
    }

    /**
     * Map URI to Controller and Action based on the
     * http method
     * @param $uri
     * @return array|bool
     */
    protected function resolveRoute($uri){
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $route = false;
        if(array_key_exists($httpMethod, $this->routes)){
            $routes = $this->routes[$httpMethod];
            $route = static::uriResolver($uri, $routes);
        }
        if(!$route){
            $route = static::uriResolver($uri, $this->routes['*']);
        }
        return $route;
    }

	protected function setAttrs(){
        $url = '';
        if(array_key_exists('PATH_INFO', $_SERVER)){
		    $url = $_SERVER['PATH_INFO'];
        }

		if(empty($url)){
			$url = "//";
		}
		if ($url[strlen($url)-1] == '/'){
			$url = substr($url, 0, -1);
		}
        $route = $this->resolveRoute($url);
        if(!$route){
            echo 'Not Found';
            exit;
        }

		$this->class = ucfirst($route[0]);
        $this->method = $route[1];
        $this->args = $route[2];

		if($_SERVER['REQUEST_METHOD'] == 'POST'){
			if(strstr($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') ||
				strstr($_SERVER['CONTENT_TYPE'] , 'multipart/form-data')){
				$this->args[] = array(&$_POST);
			}
			else if(array_key_exists('CONTENT_TYPE', $_SERVER) &&
					strstr($_SERVER['CONTENT_TYPE'], 'application/json')){
				$this->response->contentType = 'application/json';
				$request = file_get_contents("php://input");

				if(!preg_match(Router::RPC_MATCH, $request)){
					throw new \ReflectionException('Server supports the jsonrpc 2.0 protocol');
				}
				$ob = json_decode($request, true);
				$this->method = $ob['method'];
				$this->args = $ob['params'];
				$this->id = $ob['id'];
			}
			else{
				throw new \ReflectionException('Unknown content type for POST method');
			}
		}
	}

	protected function handleJsonResponse(){
		if(array_key_exists('CONTENT_TYPE', $_SERVER) &&
						strstr($_SERVER['CONTENT_TYPE'], 'application/json')){
			$payload = array(
				'jsonrpc' => '2.0',
				'result' => $this->response->body,
				'id' => $this->id
			);
            $this->response->body = json_encode($payload);
		}
	}

    /**
     * Inject the dependencies specified by the Controller class
     * Invoke the dependencies method and assign the requested
     * services.
     * @param \Edge\Controllers\BaseController $instance
     */
    protected static function setDependencies(\Edge\Controllers\BaseController $instance){
        $reflection = new \ReflectionClass($instance);
        $method = $reflection->getMethod('dependencies');
        $method->setAccessible(true);
        $deps = $method->invoke($instance);
        if(count($deps) > 0){
            $webApp = Edge::app();
            $prop = new \ReflectionProperty($instance, '_components');
            $prop->setAccessible(true);
            $val = array();
            foreach($deps as $service){
                $val[$service] = $webApp->{$service};
            }
            $prop->setValue($instance, $val);
        }
    }

    public function invoke(){
        $class = sprintf('Application\Controllers\%s', $this->class);
        $instance = new $class($this->response);
        static::setDependencies($instance);
        $instance->on_request();
        if(method_exists($instance, $this->method)){
            try{
                $processed = false;
                $retries = 0;
                $max_retries = 20;

                while(!$processed && ($retries < $max_retries)) {
                    try{
                        $retries++;
                        $instance->preProcess();
                        /*if(!$context->loadedFromCache){
                            $context->response->body = $this->invokeRequest();
                            $this->handleJsonResponse();
                        }*/
                        $this->response->body = call_user_func_array(array($instance, $this->method), $this->args);
                        $instance->postProcess();
                        /*if($context->autoCommit){
                            $_db = Database\MysqlMaster::getInstance();
                            $_db->commit();
                        }*/
                        $processed = true;
                    }catch(DeadLockException $e) {
                        Logger::log('RETRYING');
                        usleep(100);
                    }
                }
                if(!$processed) {
                    Logger::log('DEADLOCK ERROR');
                    throw new Exception('Deadlock detected');
                }
            }
            catch(UnauthorizedException $e){
                $this->response->httpCode = 401;
            }
            catch(\ReflectionException $e){
                Logger::log($e->getMessage());
                $this->handle404Error();
            }
            catch(\Exception $e){
                Logger::log($e->getMessage());
                $this->handleServerError();
            }
        }
        $this->response->write();
    }
}