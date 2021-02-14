<?php

class Search extends SearchCore
{

    public static function find(
        $id_lang = 1,
        $expr,
        $page_number = 1,
        $page_size = 1,
        $order_by = 'position',
        $order_way = 'desc',
        $ajax = false,
        $use_cookie = true,
        Context $context = null
    )
    {

        if (!$context) {
            $context = Context::getContext();
        }

        $search_text = mb_strtolower(isset($_REQUEST['s'])?$_REQUEST['s']:$_REQUEST['search_query'], 'UTF-8');

        $id_shop = (int)$context->shop->id;
        $cache_id = 'sphinxSearchProduct_'.$id_lang.'_'.$id_shop.'_'.md5($search_text);
        if (Cache::isStored($cache_id)) {
            return Cache::retrieve($cache_id);
        }

        // TODO : smart page management
        if ($page_number < 1) {
            $page_number = 1;
        }
        if ($page_size < 1) {
            $page_size = 1;
        }

        if (!Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way)) {
            return false;
        }

        $start = ($page_number - 1) * $page_size;
        if (is_numeric($_REQUEST['s']) && $_REQUEST['s'] < 999999) {
            $sphinx_results = array('reference' => (int)$_REQUEST['s'], 'results' => array((int)$_REQUEST['s']), 'total' => 1);
        } else {
            // Sphinx search, get ids of found products
            $sphinx_results = self::getSphinxResults($id_lang, $search_text, $start, $page_size, $context);
            if (empty($sphinx_results['total'])) {
                $res = parent::find($id_lang, $expr, $page_number, $page_size, $order_by, $order_way, $ajax, $use_cookie, $context);
                Cache::store($cache_id, $res);
                return $res;
            }
        }
        header('X-Total: ' . $sphinx_results['total']);

        if (empty($sphinx_results['total'])) {
            Cache::store($cache_id, array());
            return $ajax ? array() : array('total' => 0, 'result' => array());
        }

        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        if (isset($sphinx_results['reference']) && $sphinx_results['reference']>0) {
            $score = ', p.reference as position';
        }else{
            $score = ', FIND_IN_SET(product_shop.id_product,"' . implode(',', array_reverse($sphinx_results['results'])) . '") as position';
        }

        // get products by id if something found
        if ($ajax) {
            $sql = 'SELECT DISTINCT p.id_product, pl.name pname, cl.name cname,
                    cl.link_rewrite crewrite, pl.link_rewrite prewrite ' . $score . '
                FROM ' . _DB_PREFIX_ . 'product p
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                    p.`id_product` = pl.`id_product`
                    AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . '
                )
                ' . Shop::addSqlAssociation('product', 'p') . '
                INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (
                    product_shop.`id_category_default` = cl.`id_category`
                    AND cl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('cl') . '
                ) ';
            if (isset($sphinx_results['reference']) && $sphinx_results['reference']>0){
                $sql .= 'WHERE p.`reference` =' . (int)$sphinx_results['reference'].' ';
            } else {
                $sql .= 'WHERE p.`id_product` IN(' . implode(',', $sphinx_results['results']) . ') ';
            }
            $sql .= 'ORDER BY position DESC LIMIT 10';

            return $db->executeS($sql, true, false);
        }

        if (strpos($order_by, '.') > 0) {
            $order_by = explode('.', $order_by);
            $order_by = pSQL($order_by[0]) . '.`' . pSQL($order_by[1]) . '`';
        }
        $alias = '';
        if ($order_by == 'price') {
            $alias = 'product_shop.';
        } elseif (in_array($order_by, array('date_upd', 'date_add'))) {
            $alias = 'p.';
        }
        $sql = 'SELECT p.*, product_shop.*, ' ./*'stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity,'*/
            'pl.`description_short`, pl.`available_now`, pl.`available_later`, pl.`link_rewrite`, pl.`name`,
            image_shop.`id_image` id_image, il.`legend`, m.`name` manufacturer_name ' . $score . ',
            DATEDIFF(
                p.`date_add`,
                DATE_SUB(
                    "' . date('Y-m-d') . ' 00:00:00",
                    INTERVAL ' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
                )
            ) > 0 new' . (Combination::isFeatureActive() ? ', product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, IFNULL(product_attribute_shop.`id_product_attribute`,0) id_product_attribute' : '') . '
            FROM ' . _DB_PREFIX_ . 'product p
            ' . Shop::addSqlAssociation('product', 'p') . '
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                p.`id_product` = pl.`id_product`
                AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . '
            )
            ' . (Combination::isFeatureActive() ? 'LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop FORCE INDEX (id_product)
                ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')' : '') . '
            ' . /*Product::sqlStock('p', 0) .*/
        '
            LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m FORCE INDEX (PRIMARY) 
                ON m.`id_manufacturer` = p.`id_manufacturer`
            LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop FORCE INDEX (id_product)
                ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int)$context->shop->id . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int)$id_lang . ') ';

        if (isset($sphinx_results['reference']) && $sphinx_results['reference']>0) {
            $sql .= 'WHERE p.`reference` =' . (int)$sphinx_results['reference'].' ';
        } else {
            $sql .= 'WHERE p.`id_product` IN(' . implode(',', $sphinx_results['results']) . ') ';
        }
        $sql .= 'GROUP BY product_shop.id_product
				' . ($order_by ? 'ORDER BY  ' . $alias . $order_by : '') . ($order_way ? ' ' . $order_way : '') . '
				LIMIT ' . (int)(($page_number - 1) * $page_size) . ',' . (int)$page_size;
        $result = $db->executeS($sql, true, false);

        $sql_total = 'SELECT COUNT(*)
				FROM ' . _DB_PREFIX_ . 'product p
				' . Shop::addSqlAssociation('product', 'p') . '
				INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
					p.`id_product` = pl.`id_product`
					AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . '
				)
				WHERE p.`id_product` IN(' . implode(',', $sphinx_results['results']) . ')';
        $total = $db->getValue($sql_total, false);

        if (!$result) {
            $result_properties = array();
        } else {
            $result_properties = Product::getProductsProperties((int)$id_lang, $result);
        }

        $res = array('total' => $total, 'result' => $result_properties);

        Cache::store($cache_id, $res);

        return $res;
    }

    protected static function getRequestString($string)
    {
        $s = trim(mb_strtolower(strip_tags(urldecode($string))));
        $s = str_replace(
            array('&', '-', '–', '‒', '—', '―', '&', '(', ')', '/', '\\', '+', '*', '_', '!', ',', '.')
            , ' ', $s
        );
        $s = preg_replace('! . !', ' ', $s . ' ');
        $s = trim(preg_replace('![\s\t\r\n]+!', ' ', $s));

        $search_query = '';
        $aRequestString = preg_split('/[\s,-]+/', $s, 10);
        if (is_array($aRequestString) && count($aRequestString) > 1) {
            $aKeyword = array();
            foreach ($aRequestString as $i=>$sValue) {
                if (mb_strlen($sValue) < (int)Configuration::get('PS_SEARCH_MINWORDLEN')) continue;
                $aKeyword[] = '(' . $sValue .' | *' . $sValue . '* | =' . $sValue . ')';
            }
            $search_query = '(' . $s . ')';
            $search_query .= ' | (' . implode(' & ', $aKeyword) . ')';
            unset($aKeyword);
        } else {
            $search_query = '(' . $s . ' | *' . $s . '* | =' . $s . ')';
        }
        return $search_query;
    }

    public static function getSphinxResults($id_lang = 1, $search_query, $page_number=1, $page_size=1, Context $context = null)
    {
        if (!$search_query) {
            return null;
        }

        if (!$context) {
            $context = Context::getContext();
        }
        $cache_id = 'sphinxSearchResults_'.$id_lang.'_'.$context->shop->id.'_'.md5($search_query);
        if (Cache::isStored($cache_id)) {
            return Cache::retrieve($cache_id);
        }

        $results = $resultsFull = $stats = array();

        /* You should enable error reporting for mysqli before attempting to make a connection */
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        // connect to Sphinx database
        $link = mysqli_connect('manticore', 'root', '', 'rt_products', '9306');

        if ($link) {
            $match = '' . self::getRequestString($search_query) . '';

            $query = 'SELECT *, ' . pow(2,$context->shop->id-1) . ' & active AS `sort`, WEIGHT() AS `weight`'
                . ' FROM `rt_products`'
                . ' WHERE MATCH(\'@(ft_engine,ft_model,ft_make,name,reference,description,description_short,name_category) ' . $match . '\')'
                . ' AND shop_id=' . (int)$context->shop->id
                // . ' AND langId_attr=' . (int)$id_lang
                . ' ORDER BY `sort` DESC, `weight` DESC, `price` ASC'
                // . ' LIMIT '.$page_number.', '.$page_size
                . ' LIMIT 0, 10000'
                . ' OPTION max_query_time=10'
                . ', field_weights=(reference=3, name_category=2, ft_engine=2, ft_make=2, ft_model=2, name=1)'
                . ', ranker=expr(\'abs(query_word_count-doc_word_count)*sum((6*lcs+8*(min_hit_pos==1)+exact_hit*100)*user_weight)*1000+bm25\')'
                //. ', ranker=bm25'
                . ' ';

            // echo "<li>$query";

            if ($result = $link->query($query)) {
                // get count of results
                $stats = array();
                $meta = $link->query('SHOW META')->fetch_all();
                foreach ($meta as $row) $stats[$row[0]] = $row[1];
                unset($meta, $row);

                while ($query_results = $result->fetch_array()) {
                    foreach ($query_results as $i => $v) if (is_numeric($i)) unset($query_results[$i]);
                    $results['products'][] = $query_results['id'];
                }
                $result->close();
            }

            $query = 'SELECT *, WEIGHT() AS `weight` FROM `rt_categories`'
                . ' WHERE MATCH(\'' . $match . '\')'
                . ' AND shop_id=' . (int)$context->shop->id
                . ' AND lang_id=' . (int)$id_lang
                . ' ORDER BY `weight` DESC'
                // todo. msart multi page management
                //.' LIMIT '.$page_number.', '.$page_size
                . ' OPTION max_query_time=10' .
                ' ';
            if ($result = $link->query($query)) {
                while ($query_results = $result->fetch_array()) {
                    foreach ($query_results as $i => $v) if (is_numeric($i)) unset($query_results[$i]);
                    $results['categories'][$query_results['id']] = array(
                        'id' => $query_results['id'],
                        'name' => $query_results['category'],
                        'link' => $query_results['categorylink'],
                        'parentid' => $query_results['parentId'],
                    );
                }
                $result->close();
            }

            mysqli_close($link);
        } else {
            header('X-Error: server not work!');
            Cache::store($cache_id, false);

            return false;
        }

        $res = array('total' => (int)$stats['total'], 'results' => array());
        if (isset($results['products']) && is_array($results['products'])) $res['results'] = array_map('intval', $results['products']);
        if (isset($results['categories']) && is_array($results['categories'])) $res['categories'] = $results['categories'];
        Cache::store($cache_id, $res);
        return $res;
    }
    
}
