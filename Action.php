<?php

/**
 * TinyPNG Plugin
 *
 * @copyright  Copyright (c) 2017 99kyuu (http://www.moyu.win)
 * @license    GNU General Public License 2.0
 *
 */

require_once("lib/Tinify/Exception.php");
require_once("lib/Tinify/ResultMeta.php");
require_once("lib/Tinify/Result.php");
require_once("lib/Tinify/Source.php");
require_once("lib/Tinify/Client.php");
require_once("lib/Tinify.php");

class TinyPNG_Action extends Typecho_Widget implements Widget_Interface_Do
{


    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);

    }

    /**
     * 查询状态
     *
     */
    public function status()
    {
        $apikey = Helper::options()->plugin("TinyPNG")->apikey;
        \Tinify\setKey($apikey);
        \Tinify\validate();
        $compressionsThisMonth = \Tinify\compressionCount();
        $possess = 500 - $compressionsThisMonth;

        echo $possess;
    }

    public function test(){
        echo "test";
    }

    public function action()
    {
        $this->on($this->request->__isSet('status'))->status();
        $this->on($this->request->is('test'))->test();
       // $this->response->goBack();
    }
}

?>