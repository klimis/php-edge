<?php
namespace Edge\Controllers;

use Edge\Core,
    Edge\Core\View\Layout,
    Edge\Core\Interfaces;

abstract class BaseController{

	protected static $css = array();
	protected static $js = array();
    protected static $layout = null;
    protected static $defaultLayoutAttrs = [
        'title' => 'Edge PHP Framework'
    ];

    /**
     * Load the layout file
     * This method is commonly overridden by child
     * controllers, in order to specify common
     * blocks, like headers, footers, van menus etc
     * @return Layout
     */
    protected static function getLayout(){
        $file = static::getTemplateFile(static::$layout);
        return new Layout($file, static::$js, static::$css);
    }

    /**
     * Return the URL from the routes array that corresponds
     * to the selected attributes
     * ie
     * parent::createLink(get_called_class(), 'updateRole', [':id' => $role->id], 'POST');
     * or
     * parent::createLink('Application\Controllers\Home', 'index', ["anchor"=>"#list_products"])
     * @param $controller
     * @param $action
     * @param array $attrs
     * @param string $method
     * @return string
     */
    public static function createLink($controller, $action, array $attrs=array(), $method='GET'){
        return Core\Edge::app()->router->createLink($controller, $action, $attrs, $method);
    }

    /**
     * Render the layout file
     * @param Core\Template $tpl
     * @param array $attrs
     * @return mixed|string
     */
    protected static function render(Core\View\Template $tpl, $attrs=array()){
        if(!static::$layout){
            throw new Core\Exceptions\EdgeException("Layout template must be defined by class ". get_called_class());
        }
        $layout = static::getLayout();
        $attrs = array_merge(static::$defaultLayoutAttrs, $attrs);
        $layout->title = $attrs['title'];
        $layout->body = $tpl->parse();
        return $layout->parse();
    }

    /**
     * Return the path to the template file.
     * The path is resolved based on the caller.
     * So if a call to getTemplateFile('ui.index.tpl') was made
     * from the class Home which is located at
     * Application/Controllers/Home.php then the template file
     * is looked up in Application/Views/ui.index.tpl
     * @param $file (ie ui.index.tpl)
     * @return string
     */
    private static function getTemplateFile($file){
        return sprintf("../%s/Views/%s", explode("\\", get_called_class())[0], $file);
    }

    /**
     * Define filters to be run before and after the request
     * has been processed. Filters must extend
     * Edge\Core\Filters\BaseFilter class
     * and implement 2 methods
     * preProcess($response, $request) and
     * postProcess($response, $request)
     *
     * Here we define a Post Process filter that replaces
     * content within caches, that has been declared as
     * dynamic
     *
     * Example usage from a child class
     * return array_merge(parent::filters(), array(
        array(
            'Edge\Core\Filters\PageCache',
            'ttl' => 10*60,
            'varyBy' => 'url',
            'cacheValidator' => new Validator\QueryValidator("SELECT COUNT(id) FROM users"),
            'applyTo' => array('index')
         ),
         array('Edge\Core\Filters\DynamicOutput')
        ));
     * @return array
     */
    public function filters(){
        return array();
    }

    /**
     * Load the specified template file
     * @param $file
     * @param array $cacheAttrs
     * @return Core\Template
     */
    protected static function loadView($file, $cacheAttrs=array()){
        return new Core\View\Template(static::getTemplateFile($file), $cacheAttrs);
    }

	/**
	 *
	 * Generic method to handle 404
	 * http codes.
	 * Should be overwritten by apps
	 * to provide more details
	 * @param string $url
	 */
	public function notFound($url) {
		return 'URL '.$url.' not found';
	}

	/**
	 *
	 * Generic method to handle 50x
	 * http codes.Should be overwritten by apps
	 * to provide more details
	 * @param string $http_method
	 */
	public function serverError($message) {
		return $message;
	}

    public function authorize($username, $password){
        $userClass = Core\Edge::app()->getConfig('userClass');
        $user = $userClass::getUserByUsername($username);
        if($user && $user->authenticate($password)){
            Core\Edge::app()->session->regenerate();
            Core\Edge::app()->user($user);
            return true;
        }
        return false;
    }

	/**
	 *
	 * Logout the user and destroy
	 * the session
	 */
	public function logout() {
        $app = Core\Edge::app();
		$app->session->destroy();
        $app->user(\Edge\Models\User::getUserByUsername("guest"));
		return true;
	}
}