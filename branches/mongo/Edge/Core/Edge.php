<?php
namespace Edge\Core;
use Edge\Core\Pimple,
    Edge\Models\User;

/**
 * Class responsible for loading configurations options and
 * bootstrapping the application
 */
class Edge{

    private $container;
    private static $__instance;
    private $routes;
    private $config = array();
    public $router;

    public function __construct($config){
        if(!is_null(static::$__instance)){
            throw new \Edge\Core\Exceptions\EdgeException("Only one instance of the Web App can exist");
        }
        $defaults = include(__DIR__.'/../Config/config.php');
        $defaultRoutes = include(__DIR__.'/../Config/routes.php');
        if(is_string($config)){
            $config = include($config);
        }
        $config = array_replace_recursive($defaults, $config);
        if($config['env'] == 'development'){
            ini_set('display_errors', 'On');
            error_reporting(E_ALL);
        }else{
            ini_set('display_errors', 'Off');
        }
        date_default_timezone_set($config['timezone']);
        $this->container = new Pimple();
        $this->registerServices($config['services']);
        $this->routes = array_merge_recursive($defaultRoutes, $config['routes']);
        unset($config['services'], $config['routes']);
        $this->config = $config;
        self::$__instance = $this;
    }

    /**
     * Get or set the current user
     * If no userID variable exists in the session
     * load the Guest user
     * This method caches the object once it loads the first time
     * @param User $user
     * @return User
     */
    public function user(User $user=null){
        static $instance;
        if($user){
            $this->session->userID = $user->id;
            $instance = $user;
        }
        elseif(is_null($instance)){
            $userID = $this->session->userID;
            $class = $this->getConfig('userClass');
            if(!isset($userID)){
                $userID = $class::GUEST;
            }
            $instance = $class::getUserById($userID);
        }
        return $instance;
    }

    /**
     * Register the services specified in the config
     * file. These services reside in an IoC object
     * and are not initialized until they are invoked
     * @param array $services
     */
    protected function registerServices(array $services){
        foreach($services as $name=>$params){
            if(is_array($params) && array_key_exists('invokable', $params)){
                $shared = array_key_exists('shared', $params)?$params['shared']:false;
                if(array_key_exists('invokable', $params) && is_callable($params['invokable'])){
                    $value = $params['invokable'];
                }else{
                    $value = function($c) use ($params){
                        $class = new \ReflectionClass($params['invokable']);
                        return $class->newInstanceArgs($params['args']);
                    };
                }
            }
            else{
                $shared = false;
                $value = $params;
            }
            if($shared){
                $this->container[$name] = $this->container->share($value);
            }else{
                $this->container[$name] = $value;
            }
        }
    }

    /**
     * Return a configuration variable
     * @param $name
     * @return mixed
     */
    public function getConfig($name){
        return $this->config[$name];
    }

    /**
     * Return a registered service or variable
     * @param $service
     * @return mixed
     */
    public function __get($service){
        return $this->container[$service];
    }

    /**
     * Return the routes
     * @return array
     */
    public function getRoutes(){
        return $this->routes;
    }

    /**
     * Return the web app singleton
     * @return Edge
     */
    public static function app(){
        return self::$__instance;
    }
}