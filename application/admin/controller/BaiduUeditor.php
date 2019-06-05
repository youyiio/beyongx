<?php
/**
* 百度编辑器控制器
*/
namespace app\admin\controller;

use think\Controller;
use think\facade\Env;
use think\Image;

class BaiduUeditor extends Controller
{

    private $thumb;//缩略图模式(看手册)
    private $water; //是否加水印(0:无水印,1:水印文字,2水印图片)
    private $waterText;//水印文字
    private $waterPosition;//水印位置

    private $rootPath; //保存的根目录，默认为public/upload
    private $savePath; //保存位置
    private $userid; //操作用户名


    public function initialize()
    {
        // $this->userid=empty($_SESSION['uid'])? 'nobody' : $_SESSION['uid'];
        $this->userid = "";

        $this->rootPath = Env::get('root_path').'public';
        $this->savePath = DIRECTORY_SEPARATOR . 'upload'.DIRECTORY_SEPARATOR . $this->userid;
        $this->thumb = 1;
        $this->waterText = get_config('domain_name');
        if (!empty($this->waterText)) {
            $this->water = 1;
        }
        $this->waterPosition= 9;
    }

    public function index()
    {
        $CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents(Env::get('config_path')."config.json")), true);
        $action = htmlspecialchars($_GET['action']);
        switch ($action) {
            case 'config':
                $result =  json_encode($CONFIG);
                break;

            /* 上传图片 */
            case 'uploadimage':
                $config = array(
                    "pathFormat" => $CONFIG['imagePathFormat'],
                    "maxSize" => $CONFIG['imageMaxSize'],
                    "allowFiles" => $CONFIG['imageAllowFiles']
                );
                $fieldName = $CONFIG['imageFieldName'];
                $result=$this->upFile($config, $fieldName);
                break;

            /* 上传涂鸦 */
            case 'uploadscrawl':
                $config = array(
                    "pathFormat" => $CONFIG['scrawlPathFormat'],
                    "maxSize" => $CONFIG['scrawlMaxSize'],
                    "allowFiles" => $CONFIG['scrawlAllowFiles'],
                    "oriName" => "scrawl.png"
                );
                $fieldName = $CONFIG['scrawlFieldName'];
                $base64 = "base64";
                $result=$this->upBase64($config,$fieldName);
                break;

            /* 上传视频 */
            case 'uploadvideo':
                $config = array(
                    "pathFormat" => $CONFIG['videoPathFormat'],
                    "maxSize" => $CONFIG['videoMaxSize'],
                    "allowFiles" => $CONFIG['videoAllowFiles']
                );
                $fieldName = $CONFIG['videoFieldName'];
                $result=$this->upFile($config, $fieldName);
                break;

            /* 上传文件 */
            case 'uploadfile':
               // default:
                $config = array(
                    "pathFormat" => $CONFIG['filePathFormat'],
                    "maxSize" => $CONFIG['fileMaxSize'],
                    "allowFiles" => $CONFIG['fileAllowFiles']
                );
                $fieldName = $CONFIG['fileFieldName'];
                $result=$this->upFile($config, $fieldName);
                break;

            /* 列出图片 */
            case 'listimage':
                $allowFiles = $CONFIG['imageManagerAllowFiles'];
                $listSize = $CONFIG['imageManagerListSize'];
                $path = $CONFIG['imageManagerListPath'];
                $get=$_GET;
                $result =$this->file_list($allowFiles, $listSize, $get);
                        break;
                /* 列出文件 */
            case 'listfile':
                $allowFiles = $CONFIG['fileManagerAllowFiles'];
                $listSize = $CONFIG['fileManagerListSize'];
                $path = $CONFIG['fileManagerListPath'];
                $get=$_GET;
                $result =$this->file_list($allowFiles, $listSize, $get);
                break;

            /* 抓取远程文件 */
            case 'catchimage':
                $config = array(
                    "pathFormat" => $CONFIG['catcherPathFormat'],
                    "maxSize" => $CONFIG['catcherMaxSize'],
                    "allowFiles" => $CONFIG['catcherAllowFiles'],
                    "oriName" => "remote.png"
               );
                $fieldName = $CONFIG['catcherFieldName'];
                /* 抓取远程图片 */
                $list = array();
                if (isset($_POST[$fieldName])) {
                    $source = $_POST[$fieldName];
                } else {
                    $source = $_GET[$fieldName];
                }
                foreach ($source as $imgUrl) {
                    $info=json_decode($this->saveRemote($config, $imgUrl),true);
                    if ($info && isset($info["url"])) {
                        array_push($list, array(
                            "state" => $info["state"],
                            "url" => $info["url"],
                            "size" => $info["size"],
                            "title" => htmlspecialchars($info["title"]),
                            "original" => htmlspecialchars($info["original"]),
                            "source" => htmlspecialchars($imgUrl)
                        ));
                    }
                }

                $result= json_encode(array(
                    'state'=> count($list) ? 'SUCCESS':'ERROR',
                    'list'=> $list
                ));
                break;

            default:
                $result = json_encode(array(
                    'state'=> '请求地址出错'
                ));
                break;
        }

        /* 输出结果 */
        if (isset($_GET["callback"])) {
            if (preg_match("/^[\w_]+$/", $_GET["callback"])) {
                echo htmlspecialchars($_GET["callback"]) . '(' . $result . ')';
            } else {
                echo json_encode(array(
                    'state'=> 'callback参数不合法'
                ));
            }
        } else {
            echo $result;
        }

    }
    /**
     * 上传文件的主处理方法
     * @return mixed
     */
    private function upFile($config, $fieldName){

        $validate = ['size' => $config['maxSize'],'ext' => $this->format_exts($config['allowFiles'])];

        $dirname = $this->rootPath . $this->savePath;
        $file = request()->file('upfile');

        $info = $file->move($dirname);//tp方法会自动加上日期date('Ymd');$info->getSaveName()为date('Ymd')/name.ext;
        $savePath = $this->savePath;
        if ($info) {

            $fname = $dirname.DIRECTORY_SEPARATOR.$info->getSaveName();
            $imagearr = explode(',', 'jpg,gif,png,jpeg,bmp,ttf,tif');
            $ext = $info->getExtension();

            $isimage = in_array($ext,$imagearr) ? 1 : 0;
            if ($isimage) {
                $image= Image::open($fname);
                $image->thumb(680,680,$this->thumb)->save($fname);
                if ($this->water==1) {
                    $image->text($this->waterText,Env::get('VENDOR_PATH').'/topthink/think-captcha/assets/zhttfs/1.ttf',10,'#FFCC66',$this->waterPosition)->save($fname);
                }
                if ($this->water==2) {
                    $image->water($this->waterImage)->save($fname);
                }
            }

            $data=array(
                'state' =>'SUCCESS',
                'url' => config('view_replace_str.__PUBLIC__').str_replace(DIRECTORY_SEPARATOR, '/', $savePath.$info->getSaveName()),
                'title' => $info->getFileName(),
                'original' => $info->getInfo('name'),
                'type' =>'.' . $ext,
                'size' => $info->getSize(),
            );
        } else {
            $data = array(
                'state'=>$file->getError(),
            );
        }
        return json_encode($data);
    }

    /**
     * 处理base64编码的图片上传
     * @return mixed
     */
    private function upBase64($config,$fieldName)
    {
        $base64Data = $_POST[$fieldName];
        $img = base64_decode($base64Data);

        $savePath = $this->savePath .date('Ymd').DIRECTORY_SEPARATOR;
        $dirname=$this->rootPath . $savePath;
        $file['filesize']=strlen($img);
        $file['oriName']=$config['oriName'];
        $file['ext']=strtolower(strrchr($config['oriName'], '.'));
        $file['name']= uniqid() .  $file['ext'];
        $file['fullName']=$dirname . $file['name'];
        $fullName=$file['fullName'];

        //检查文件大小是否超出限制
        if ($file['filesize'] >= ($config["maxSize"])) {
        $data=array(
            'state'=>'文件大小超出网站限制',
        );
        return json_encode($data);
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
               $data=array(
            'state'=>'目录创建失败',
        );
        return json_encode($data);
        } else if (!is_writeable($dirname)) {
             $data=array(
            'state'=>'目录没有写权限',
        );
        return json_encode($data);
        }

        //移动文件
        if (!(file_put_contents($fullName, $img) && file_exists($fullName))) { //移动失败
            $data=array(
                'state'=>'写入文件内容错误',
            );
        } else { //移动成功
            $data=array(
                'state'=>'SUCCESS',
                'url'=>config('view_replace_str.__PUBLIC__') . str_replace(DIRECTORY_SEPARATOR, '/', $savePath.$file['name']),
                'title'=>$file['name'],
                'original'=>$file['oriName'],
                'type'=>$file['ext'],
                'size'=>$file['filesize'],
            );
        }
        return json_encode($data);
    }

    /**
     * 拉取远程图片
     * @return mixed
     */
    private function saveRemote($config, $fieldName)
    {
        $imgUrl = htmlspecialchars($fieldName);
        $imgUrl = str_replace("&amp;", "&", $imgUrl);

        //http开头验证
        if (strpos($imgUrl, "http") !== 0) {
             $data=array(
                 'state'=>'链接不是http链接',
             );
             return json_encode($data);
        }
        //获取请求头并检测死链
        $heads = get_headers($imgUrl, true);
        if (!(stristr($heads[0], "200") && stristr($heads[0], "OK"))) {
             $data=array(
                'state'=>'链接不可用',
            );
             return json_encode($data);
        }
        //格式验证(扩展名验证和Content-Type验证)
        $fileType = strtolower(strrchr(strrchr($imgUrl,'/'), '.'));
        //img链接后缀可能为空,Content-Type须为image
        if ((!empty($fileType) && !in_array($fileType, $config['allowFiles'])) || stristr($heads['Content-Type'], "image") === -1) {
            $data=array(
                'state'=>'链接contentType不正确',
            );
             return json_encode($data);
        }

        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            array('http' => array(
                'follow_location' => false // don't follow redirects
            ))
        );
        readfile($imgUrl, false, $context);
        $img = ob_get_contents();
        ob_end_clean();
        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

        $savePath = $this->savePath .date('Ymd').DIRECTORY_SEPARATOR;
        $dirname=$this->rootPath . $savePath;
        $file['oriName']=$m ? $m[1]:"";
        $file['filesize']=strlen($img);
        $file['ext']=strtolower(strrchr($config['oriName'], '.'));
        $file['name']= uniqid() .  $file['ext'];
        $file['fullName']=$dirname . $file['name'];
        $fullName=$file['fullName'];

        //检查文件大小是否超出限制
        if ($file['filesize'] >= ($config["maxSize"])) {
            $data=array(
                'state'=>'文件大小超出网站限制',
            );
            return json_encode($data);
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $data=array(
                'state'=>'目录创建失败',
            );
            return json_encode($data);
        } else if (!is_writeable($dirname)) {
            $data=array(
                'state'=>'目录没有写权限',
            );
            return json_encode($data);
        }

        //移动文件
        if (!(file_put_contents($fullName, $img) && file_exists($fullName))) { //移动失败
            $data=array(
                'state'=>'写入文件内容错误',
            );
            return json_encode($data);
        } else { //移动成功
            $data=array(
                'state'=>'SUCCESS',
                'url'=> config('view_replace_str.__PUBLIC__') . str_replace(DIRECTORY_SEPARATOR, '/', $savePath.$file['name']),
                'title'=>$file['name'],
                'original'=>$file['oriName'],
                'type'=>$file['ext'],
                'size'=>$file['filesize'],
            );
        }
        return json_encode($data);
    }
    private function file_list($allowFiles, $listSize, $get){
        $dirname=$this->rootPath . $this->savePath ;
        if ($this->userid!='admin') {
            $dirname.=$this->userid . '/';
        }
        $allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);

        /* 获取参数 */
        $size = isset($get['size']) ? htmlspecialchars($get['size']) : $listSize;
        $start = isset($get['start']) ? htmlspecialchars($get['start']) : 0;
        $end = $start + $size;

        /* 获取文件列表 */
        // $path = $_SERVER['DOCUMENT_ROOT'] . (substr($path, 0, 1) == "/" ? "":"/") . $path;
        $path=$dirname;
        $files = $this->getfiles($path, $allowFiles);
        if (!count($files)) {
            return json_encode(array(
                "state" => "no match file",
                "list" => array(),
                "start" => $start,
                "total" => count($files)
            ));
        }

        /* 获取指定范围的列表 */
        $len = count($files);
        for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--){
            $list[] = $files[$i];
        }
        //倒序
        //for ($i = $end, $list = array(); $i < $len && $i < $end; $i++){
        //    $list[] = $files[$i];
        //}

        /* 返回数据 */
        $result = json_encode(array(
            "state" => "SUCCESS",
            "list" => $list,
            "start" => $start,
            "total" => count($files)
        ));

        return $result;
    }

    /**
     * 遍历获取目录下的指定类型的文件
     * @param $path
     * @param array $files
     * @return array
     */
    private function getfiles( $path , $allowFiles, &$files = array() ) {
        if ( !is_dir( $path ) ) return null;
        if(substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir( $path);
        while ( false !== ( $file = readdir( $handle ) ) ) {
            if ( $file != '.' && $file != '..' ) {
                $path2 = $path . $file;
                if ( is_dir( $path2)) {
                    $this->getfiles( $path2 ,$allowFiles,  $files );
                } else {
                    if (preg_match("/\.(".$allowFiles.")$/i", $file)) {
                        $files[] = array(
                            'url'=>  preg_replace('/(.*)upload/i',config('view_replace_str.__PUBLIC__').'/upload',$path2),
                            'mtime'=> filemtime($path2)
                        );
                    }
                }
            }
        }
        return $files;
    }
    /**
     * [formatUrl 格式化url，用于将getfiles返回的文件路径进行格式化，起因是中文文件名的不支持浏览]
     * @param  [type] $files [文件数组]
     * @return [type]        [格式化后的文件数组]
     */
    private function formatUrl($files){
        if(!is_array($files)) return $files;
        foreach ($files as  $key => $value) {
            $data=array();
            $data=explode('/', $value);
            foreach ($data as $k=>$v) {
                if($v!='.' && $v!='..'){
                    $data[$k]=urlencode($v);
                    $data[$k] = str_replace("+", "%20", $data[$k]);
                }
            }
            $files[$key]=implode('/', $data);
        }
        return $files;
    }


    private function format_exts($exts){
        $data=array();
        foreach ($exts as $key => $value) {
            $data[]=ltrim($value,'.');
        }
        return $data;
    }

}