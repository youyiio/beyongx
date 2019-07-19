<?php
/**
 * Created by PhpStorm.
 * User: cattong
 * Date: 2018-12-03
 * Time: 16:15
 */
namespace app\cms\controller;

use app\common\model\ArticleModel;

use app\common\model\CategoryModel;
use think\facade\Env;
use think\facade\Log;
use XMLWriter;

class Sitemap extends Base
{

    private $config = [
        "domain" => '',
        "xmlfile" => "_sitemap_", //不带后缀
//        "htmlfile" => "sitemap.html",
//        "xslfile" => "sitemap-xml.xsl",
//        "isxsl2html" => true,
        "isschemamore" => true
    ];

    // 按照层级对应优先级，第一层优先级为1，第二级为0.8，第三级为0.6
    private $priority = [
        "1" => "1",
        "2" => "0.8",
        "3" => "0.6",
        "4" => "0.5"
    ];

    private $change_freq = [
        'always', 'hourly',
        'daily', 'weekly',
        'monthly', 'yearly', 'never'
    ];
    // 文件类型
    private $file_type = [
        'php', 'html', 'xml',
        'txt', 'zip', 'pdf',
        'css', 'js', 'png', 'jpeg'
    ];

    public function index()
    {
        header("Content-type:text/xml;charset=utf-8");

        $xmlFileName = Env::get('root_path') . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . $this->config['xmlfile'];
        if (file_exists($xmlFileName . LibSitemap::SITEMAP_EXT) && cache('_sitemap_')) {
            echo file_get_contents($xmlFileName . LibSitemap::SITEMAP_EXT);
            exit;
        }

        if (file_exists($xmlFileName . LibSitemap::SITEMAP_EXT)) {
            unlink($xmlFileName . LibSitemap::SITEMAP_EXT);
        }

        // 计算生成时间
        $costTimeStart = $this->getMillisecond();

        $sitemap = new LibSitemap($this->config['domain'] ? $this->config['domain'] : config('url_domain_root'));
        $sitemap->setXmlFile($xmlFileName);	 // 设置xml文件（可选）
        $sitemap->setDomain($this->config['domain'] ? $this->config['domain'] : config('url_domain_root')); // 设置自定义的根域名（可选）
        $sitemap->setIsChemaMore($this->config['isschemamore']);	// 设置是否写入额外的Schema头信息（可选）


        //生成index 首页
        $sitemap->addItem(url('cms/Index/index'), 1, "hourly", date_time());
        $sitemap->addItem(url('cms/Index/about'), 1, "monthly", date_time());
        $sitemap->addItem(url('cms/Index/contact'), 1, "monthly", date_time());
        $sitemap->addItem(url('cms/Index/about'), 1, "monthly", date_time());

        //生成栏目item
        $CategoryModel = new CategoryModel();
        $resultSet = $CategoryModel->where(['status' => CategoryModel::STATUS_ONLINE])->order('sort asc')->select();
        foreach ($resultSet as $category) {
            $priority = $this->priority[1];
            $loc = url('cms/Article/articleList', ['cid' => $category->id]);
            $sitemap->addItem($loc, $priority, "daily", date_time());

            $loc = url('cms/Article/articleList', ['cname' => $category->title_en]);
            $sitemap->addItem($loc, $priority, "daily", date_time());
        }

        //生成文章item
        $ArticleModel = new ArticleModel();
        $where = [
            'status' => ArticleModel::STATUS_PUBLISHED
        ];
        $resultSet = $ArticleModel->where($where)->order('sort desc, id desc')->select();
        foreach ($resultSet as $article) {
            $priority = $this->priority[2];
            $loc = url('cms/Article/viewArticle', ['aid' => $article->id]);
            $sitemap->addItem($loc, $priority, "weekly", $article->update_time);
        }

        $sitemap->endSitemap();

        // 计算生成的时间
        $costTime = $this->getMillisecond() - $costTimeStart;
        $costTime= sprintf('%01.6f', $costTime);
        Log::info("生成sitemap.xml 用时 : $costTime (s)");

        cache('_sitemap_', true, 3600);

        echo file_get_contents($xmlFileName . LibSitemap::SITEMAP_EXT);
        exit;
    }

    //  获取毫秒的时间戳
    private function getMillisecond() {
        $time = explode(" ", microtime());
        return $time[1] + $time[0];
    }


}




/**
 * Sitemap
 *
 * 生成 Google Sitemap files (sitemap.xml)
 *
 * @package    Sitemap
 * @author     Sandy <sandy@mimvp.com>
 * @copyright  2009-2017 mimvp.com
 * @license    http://opensource.org/licenses/MIT MIT License
 * @link       http://github.com/mimvp/sitemap-php
 */
class LibSitemap {

    private $writer;		// XMLWriter对象
    private $domain = "http://mimvp.com";			// 网站地图根域名
    private $xmlFile = "sitemap";					// 网站地图xml文件（不含后缀.xml）
    private $xmlFileFolder = "";					// 网站地图xml文件夹
    private $currXmlFileFullPath = "";				// 网站地图xml文件当前全路径
    private $isSchemaMore= true;					// 网站地图是否添加额外的schema
    private $current_item = 0;						// 网站地图item个数（序号）
    private $current_sitemap = 0;					// 网站地图的个数（序号）

    const SCHEMA_XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    const SCHEMA_XMLNS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    const SCHEMA_XSI_SCHEMALOCATION = 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd';
    const DEFAULT_PRIORITY = 0.5;
    const SITEMAP_ITEMS = 50000;
    const SITEMAP_SEPERATOR = '-';
    const INDEX_SUFFIX = 'index';
    const SITEMAP_EXT = '.xml';

    /**
     * @param string $domain	：	初始化网站地图根域名
     */
    public function __construct($domain) {
        $this->setDomain($domain);
    }

    /**
     * 设置网站地图根域名，开头用 http:// or https://, 结尾不要反斜杠/
     * @param string $domain	：	网站地图根域名 <br>例如: http://mimvp.com
     */
    public function setDomain($domain) {
        if(substr($domain, -1) == "/") {
            $domain = substr($domain, 0, strlen($domain)-1);
        }
        $this->domain = $domain;
        return $this;
    }

    /**
     * 返回网站根域名
     */
    private function getDomain() {
        return $this->domain;
    }

    /**
     * 设置网站地图的xml文件名
     */
    public function setXmlFile($xmlFile) {
        $dir = dirname($xmlFile);
        if (!is_dir($dir)) {
            $res = mkdir(iconv("UTF-8", "GBK", $dir), 0777, true);
            if ($res) {
                echo "mkdir $dir success";
            } else {
                echo "mkdir $dir fail.";
            }
        }
        $this->xmlFile = $xmlFile;
        return $this;
    }

    /**
     * 返回网站地图的xml文件名
     */
    private function getXmlFile() {
        return $this->xmlFile;
    }

    public function setIsChemaMore($isSchemaMore) {
        $this->isSchemaMore = $isSchemaMore;
    }

    private function getIsSchemaMore() {
        return $this->isSchemaMore;
    }

    /**
     * 设置XMLWriter对象
     */
    private function setWriter(XMLWriter $writer) {
        $this->writer = $writer;
    }

    /**
     * 返回XMLWriter对象
     */
    private function getWriter() {
        return $this->writer;
    }

    /**
     * 返回网站地图的当前item
     * @return int
     */
    private function getCurrentItem() {
        return $this->current_item;
    }

    /**
     * 设置网站地图的item个数加1
     */
    private function incCurrentItem() {
        $this->current_item = $this->current_item + 1;
    }

    /**
     * 返回当前网站地图（默认50000个item则新建一个网站地图）
     * @return int
     */
    private function getCurrentSitemap() {
        return $this->current_sitemap;
    }

    /**
     * 设置网站地图个数加1
     */
    private function incCurrentSitemap() {
        $this->current_sitemap = $this->current_sitemap + 1;
    }

    private function getXMLFileFullPath() {
        $xmlfileFullPath = "";
        if ($this->getCurrentSitemap()) {
            $xmlfileFullPath = $this->getXmlFile() . self::SITEMAP_SEPERATOR . $this->getCurrentSitemap() . self::SITEMAP_EXT;	// 第n个网站地图xml文件名 + -n + 后缀.xml
        } else {
            $xmlfileFullPath = $this->getXmlFile() . self::SITEMAP_EXT;	// 第一个网站地图xml文件名 + 后缀.xml
        }
        $this->setCurrXmlFileFullPath($xmlfileFullPath);		// 保存当前xml文件全路径
        return $xmlfileFullPath;
    }

    public function getCurrXmlFileFullPath() {
        return $this->currXmlFileFullPath;
    }

    private function setCurrXmlFileFullPath($currXmlFileFullPath) {
        $this->currXmlFileFullPath = $currXmlFileFullPath;
    }

    /**
     * Prepares sitemap XML document
     */
    private function startSitemap() {
        $this->setWriter(new XMLWriter());
        $this->getWriter()->openURI($this->getXMLFileFullPath());	// 获取xml文件全路径

        $this->getWriter()->startDocument('1.0', 'UTF-8');
        $this->getWriter()->setIndentString("\t");
        $this->getWriter()->setIndent(true);
        $this->getWriter()->startElement('urlset');
        if($this->getIsSchemaMore()) {
            $this->getWriter()->writeAttribute('xmlns:xsi', self::SCHEMA_XMLNS_XSI);
            $this->getWriter()->writeAttribute('xsi:schemaLocation', self::SCHEMA_XSI_SCHEMALOCATION);
        }
        $this->getWriter()->writeAttribute('xmlns', self::SCHEMA_XMLNS);
    }

    /**
     * 写入item元素，url、loc、priority字段必选，changefreq、lastmod可选
     */
    public function addItem($loc, $priority = self::DEFAULT_PRIORITY, $changefreq = NULL, $lastmod = NULL) {
        if (($this->getCurrentItem() % self::SITEMAP_ITEMS) == 0) {
            if ($this->getWriter() instanceof XMLWriter) {
                $this->endSitemap();
            }
            $this->startSitemap();
            $this->incCurrentSitemap();
        }
        $this->incCurrentItem();
        $this->getWriter()->startElement('url');
        $newLoc = strpos($loc, 'http') === 0 ? $loc : $this->getDomain() . $loc;
        $this->getWriter()->writeElement('loc', $newLoc);			// 必选
        $this->getWriter()->writeElement('priority', $priority);					// 必选
        if ($changefreq) {
            $this->getWriter()->writeElement('changefreq', $changefreq);			// 可选
        }
        if ($lastmod) {
            $this->getWriter()->writeElement('lastmod', $this->getLastModifiedDate($lastmod));	// 可选
        }
        $this->getWriter()->endElement();
        return $this;
    }

    /**
     * 转义时间格式，返回时间格式为 2016-09-12
     */
    private function getLastModifiedDate($date=null) {
        if(null == $date) {
            $date = time();
        }
        if (ctype_digit($date)) {
            return date('c', $date);	// Y-m-d
        } else {
            $date = strtotime($date);
            return date('c', $date);
        }
    }

    /**
     * 结束网站xml文档，配合开始xml文档使用
     */
    public function endSitemap() {
        if (!$this->getWriter()) {
            $this->startSitemap();
        }
        $this->getWriter()->endElement();
        $this->getWriter()->endDocument();
        $this->getWriter()->flush();
    }

    /**
     * Writes Google sitemap index for generated sitemap files
     *
     * @param string $loc Accessible URL path of sitemaps
     * @param string|int $lastmod The date of last modification of sitemap. Unix timestamp or any English textual datetime description.
     */
    public function createSitemapIndex($loc, $lastmod = 'Today') {
        $indexwriter = new XMLWriter();
        $indexwriter->openURI($this->getXmlFile() . self::SITEMAP_SEPERATOR . self::INDEX_SUFFIX . self::SITEMAP_EXT);
        $indexwriter->startDocument('1.0', 'UTF-8');
        $indexwriter->setIndent(true);
        $indexwriter->startElement('sitemapindex');
        $indexwriter->writeAttribute('xmlns:xsi', self::SCHEMA_XMLNS_XSI);
        $indexwriter->writeAttribute('xsi:schemaLocation', self::SCHEMA_XSI_SCHEMALOCATION);
        $indexwriter->writeAttribute('xmlns', self::SCHEMA_XMLNS);
        for ($index = 0; $index < $this->getCurrentSitemap(); $index++) {
            $indexwriter->startElement('sitemap');
            $indexwriter->writeElement('loc', $loc . $this->getFilename() . ($index ? self::SITEMAP_SEPERATOR . $index : '') . self::SITEMAP_EXT);
            $indexwriter->writeElement('lastmod', $this->getLastModifiedDate($lastmod));
            $indexwriter->endElement();
        }
        $indexwriter->endElement();
        $indexwriter->endDocument();
    }

}