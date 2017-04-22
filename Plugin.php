<?php

/**
 * TinyPNG For Typecho，JPG、PNG图片格式低损压缩
 *
 * @package TinyPNG
 * @author 玖玖kyuu
 * @version 0.01
 * @link https://www.moyu.win
 * @date 2017.1.8
 */

require_once("lib/Tinify/Exception.php");
require_once("lib/Tinify/ResultMeta.php");
require_once("lib/Tinify/Result.php");
require_once("lib/Tinify/Source.php");
require_once("lib/Tinify/Client.php");
require_once("lib/Tinify.php");


class TinyPNG_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 启用插件方法,如果启用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        //文件的上传
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('TinyPNG_Plugin', 'uploadHandle');
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('TinyPNG_Plugin', 'bottom');
        //Action
        Helper::addAction('tinypng', 'TinyPNG_Action');
        return "插件已开启，请进行配置";
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeAction('tinypng');
        return "已移除";
    }

    /**
     * 获取插件配置面板
     *
     * @static
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $apikey = new Typecho_Widget_Helper_Form_Element_Text('apikey',
            NULL, '',
            _t('API key：'),
            _t('<a href="https://tinypng.com/developers" target="_blank">获取API key</a> 输入昵称、邮箱获取，部分邮箱不可申请（如QQ邮箱）'));
        $form->addInput($apikey);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {

    }

    /*
     * 文件上传
     * @param $file
     *
     */
    public static function uploadHandle($file)
    {
        //文件名是否空
        if (empty($file['name'])) {
            return false;
        }
        //文件名安全性检测
        $ext = self::getTypechoMethod("getSafeName", $file['name']);
        //是否设置运行上传的类型
        if (!self::getTypechoMethod("checkFileType", $ext) || Typecho_Common::isAppEngine()) {
            return false;
        }
        //判断格式是否PNG或者JPG
        $ext = strtolower($ext);
        $isimage = in_array($ext, array("jpg", "png", "jpeg"));
        //建立路径包含文件名
        $options = Typecho_Widget::widget('Widget_Options');
        if (empty($date)) {
            $date = new Typecho_Date($options->gmtTime);
        }
        $path = Typecho_Common::url(defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : Widget_Upload::UPLOAD_DIR,
                defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__)
            . '/' . $date->year . '/' . $date->month;
        //如果没有临时文件，则退出
        if (!isset($file['tmp_name'])) {
            return false;
        }
        if ($isimage) {
            //获取key 上传到tinypng
            $apikey = Helper::options()->plugin("TinyPNG")->apikey;
            \Tinify\setKey($apikey);
            $source = \Tinify\fromFile($file['tmp_name']);
        }
        //创建上传目录
        if (!is_dir($path)) {
            if (!self::getTypechoMethod("makeUploadDir", $path)) {
                return false;
            }
        }
        //获取文件名
        $fileName = sprintf('%u', crc32(uniqid())) . '.' . $ext;
        $path = $path . '/' . $fileName;
        if (!$isimage) {
            if (isset($file['tmp_name'])) {
                //移动上传文件
                if (!@move_uploaded_file($file['tmp_name'], $path)) {
                    return false;
                }
            } else if (isset($file['bytes'])) {
                //直接写入文件
                if (!file_put_contents($path, $file['bytes'])) {
                    return false;
                }
            } else {
                return false;
            }
        }
        if ($isimage) {
            $source->toFile($path);
            $file['size'] = filesize($path);
        } else if (!isset($file['size'])) {
            $file['size'] = filesize($path);
        }
        //返回相对存储路径
        return array(
            'name' => $file['name'],
            'path' => (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : Widget_Upload::UPLOAD_DIR)
                . '/' . $date->year . '/' . $date->month . '/' . $fileName,
            'size' => $file['size'],
            'type' => $ext,
            'mime' => Typecho_Common::mimeContentType($path)
        );


    }

    /**
     * 反射获取Widget_Upload类的方法
     * @param $methodName
     * @param $inParams
     * @return mixed
     */
    public static function getTypechoMethod($methodName, $inParams)
    {
        if (empty($objReflectClass)) {
            $objReflectClass = new ReflectionClass("Widget_Upload");
        }
        $method = $objReflectClass->getMethod($methodName);
        $method->setAccessible(true);
        if (empty($instance)) {
            $instance = new Widget_Upload(NULL, NULL);
        }
        if ("getSafeName" == $methodName) {
            $result = $method->invokeArgs($instance, array(&$inParams));
        } else {
            $result = $method->invokeArgs($instance, array($inParams));
        }
        return $result;
    }

    /**
     * 异步更新可用压缩数
     * @param $post
     */
    public static function bottom($post)
    {
        $options = Helper::options();
        $statusPath = Typecho_Common::url("/action/tinypng?status", $options->index);

        echo <<<EOT
        <script>
$("#tab-files").append("<div id=\"upload-panel\" class=\"p\">     <div class=\"upload-area tinypngstatus\" draggable=\"true\" title=\"点击更新\" style=\"position: relative;cursor: pointer;\">TinyPNG图片压缩可用 查询中...</div> </div>");
//附件按钮点击
function statusfind() {
	$.ajax({
		url: "{$statusPath}",
		async: true,
		dataType: "text",
		global: true,
		success: function(result) {
		    //如果正常返回数字
		    if(!isNaN(result)){
		        $(".tinypngstatus").text("TinyPNG图片压缩可用："+result+"张");
		    }else{
		          var sear=new RegExp('Provide an API key');
		          var unknownerror = true;
　　              if(sear.test(result)){
                      unknownerror = false;
    　　             $(".tinypngstatus").html("<span style=\"color:#df4068;\">TinyPNG 的API key没有设置！</span>");
　　              }
                  //未知错误
                  if(unknownerror){
                    $(".tinypngstatus").html("<span style=\"color:#df4068;\">TinyPNG 产生未知错误 可以在www.moyu.win反馈</span>");
                  }
		    }	    
		},
		error:function(result) {
			$(".tinypngstatus").html("<span style=\"color:#df4068;\">TinyPNG状态获取失败，网络故障或者配置错误</span>");
		}
	});
}
$("#tab-files-btn").click(statusfind);
//点击更新数
function statusfindsession(){
     $(".tinypngstatus").text("TinyPNG图片压缩可用 查询中...");
    statusfind();
}
$(".tinypngstatus").click(statusfindsession);
</script>\n
EOT;
    }
}