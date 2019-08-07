<?php
/**
 * Created by PhpStorm.
 * User: cattong
 * Date: 2019-08-05
 * Time: 14:57
 */

namespace app\cms\controller;


class Search extends Base
{

    //搜索词：q, 分页：p；路由为 aq/:q/[:p] 模式, aq为article query
    public function index($q='', $p=1)
    {
        if (empty($q)) {
            $this->error('请输入搜索词!');
        }

        if (true) {
            $this->_searchFromDb($q, $p);
        } else {
            $this->_searchFromES($q, $p);
        }

        $this->fetch("search/result");
    }

    //从数据库搜索
    private function _searchFromDb($q='', $p='')
    {
        $where = [];
        $where[] = ['status', '=', \app\common\model\ArticleModel::STATUS_PUBLISHED];

        $ArticleModel = new \app\common\model\ArticleModel();
        $field = 'id,title,thumb_image_id,description,author,post_time';
        $order = 'is_top desc,sort,post_time desc';
        $pageConfig = [
            'query' => input('param.'),
            'page' => $p
        ];
        $resultSet = $ArticleModel->where($where)->whereLike('title','%' . $q . '%','and')->field($field)->order($order)->paginate(10,false, $pageConfig);

        $this->assign('list', $resultSet);
        $this->assign('page', $resultSet->render());
    }

    //从ElasticSearch搜索
    private function _searchFromES($q='', $p='')
    {

    }

    //记录用户搜索日志
    private function _searchLog($q='', $p=1)
    {

    }
}