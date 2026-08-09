<?php

namespace app\common\model\page;

use app\common\model\BaseModel;

/**
 * diy页面模型
 */
class Page extends BaseModel
{
    protected $pk = 'page_id';
    protected $name = 'page';

    /**
     * 页面标题栏默认数据
     * @return array
     */
    public function getDefaultPage()
    {
        static $defaultPage = [];
        if (!empty($defaultPage)) return $defaultPage;
        return [
            'type' => 'page',
            'name' => '页面设置',
            'params' => [
                'name' => '页面名称',
                'title' => '页面标题',
                'title_type' => 'text',//text文字 image图片
                'share_title' => '分享标题',
                'share_img' => self::$base_url . 'image/diy/logo.png',
                'toplogo' => self::$base_url . 'image/diy/logo_top.png',
                'icon' => 'icon-biaoti',
            ],
            'style' => [
                'titleTextColor' => 'black',
                'titleBackgroundColor' => '#ff4c01',
                'hide_search' => 0
            ],
            'category' => [
                'open' => 0,
                'color' => '#000000',
            ]
        ];
    }

    /**
     * 个人中心页面diy元素默认数据
     * @return array[]
     */
    public function getCenterDefaultItems()
    {
        $data = [
            'base' => [
                'name' => '基础信息',
                'type' => 'base',
                'group' => 'page',
                'icon' => 'icon-jibenxinxi',
                'style' => [
                    'background' => '#ffffff',
                    'padding' => 48,
                    'paddingTop' => 0,
                    'paddingBottom' => 0,
                    'paddingLeft' => 0,
                    'bgcolor' => '#f2f2f2',
                    'type' => 1
                ],
            ],
            'order' => [
                'name' => '我的订单',
                'type' => 'order',
                'group' => 'page',
                'icon' => 'icon-wodedingdan',
                'style' => [
                    'background' => '#ffffff',
                    'paddingTop' => 0,
                    'paddingBottom' => 0,
                    'paddingLeft' => 10,
                    'bgcolor' => '#f2f2f2',
                    'topRadio' => 0,
                    'bottomRadio' => 10,
                    'type' => 1
                ],
            ],
            'imageSingle' => [
                'name' => '单图组',
                'type' => 'imageSingle',
                'group' => 'media',
                'icon' => 'icon-tupian111',
                'style' => [
                    'paddingTop' => 0,
                    'paddingLeft' => 0,
                    'background' => '#ffffff'
                ],
                'data' => [
                    [
                        'imgUrl' => self::$base_url . 'image/diy/banner/01.png',
                        'imgName' => 'image-1.jpg',
                        'linkUrl' => ''
                    ]
                ]
            ],
            'navBar' => [
                'name' => '导航组',
                'type' => 'navBar',
                'group' => 'media',
                'icon' => 'icon-mulu',
                'style' => [
                    'background' => '#ffffff',
                    'rowsNum' => 4
                ],
                'data' => [
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/01.png',
                        'imgName' => 'icon-1.png',
                        'linkUrl' => '',
                        'text' => '按钮文字1',
                        'color' => '#666666'
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/02.png',
                        'imgName' => 'icon-2.jpg',
                        'linkUrl' => '',
                        'text' => '按钮文字2',
                        'color' => '#666666'
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/03.png',
                        'imgName' => 'icon-3.jpg',
                        'linkUrl' => '',
                        'text' => '按钮文字3',
                        'color' => '#666666'
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/04.png',
                        'imgName' => 'icon-4.jpg',
                        'linkUrl' => '',
                        'text' => '按钮文字4',
                        'color' => '#666666'
                    ]
                ]
            ],
            'title' => [
                'name' => '标题',
                'type' => 'title',
                'group' => 'media',
                'icon' => 'icon-biaoti',
                'style' => [
                    'paddingTop' => 0,
                    'paddingBottom' => 0,
                    'paddingLeft' => 0,
                    'topRadio' => 0,
                    'bottomRadio' => 0,
                    'bgcolor' => '#FFFFFF',
                    'textSize' => 20,
                    'weight' => 800,
                    'isLine' => 1,
                    'lineColor' => '#FF0000',
                    'isSub' => 1,
                    'subtextSize' => 14,
                    'subtextColor' => '#DDDDDD',
                    'subbackground' => '#FFCCCC',
                    'isMore' => 1,
                    'moretextColor' => '#FF0000',
                    'background' => '#F5F5F5',
                    'textColor' => '#FF0000',
                    'type' => '1'
                ],
                'params' => [
                    'title' => '标题名称',
                    'subtitle' => '副标题名称',
                    'moretitle' => '更多',
                    'show_icon' => 'yes',
                    'icon' => '',
                    'linkUrl' => '',
                    'sublinkUrl' => ''
                ]
            ],
            'blank' => [
                'name' => '辅助空白',
                'type' => 'blank',
                'group' => 'tools',
                'icon' => 'icon-kongbaiye',
                'style' => [
                    'height' => 20,
                    'background' => '#ffffff'
                ]
            ],
            'guide' => [
                'name' => '辅助线',
                'type' => 'guide',
                'group' => 'tools',
                'icon' => 'icon-fuzhuxian',
                'style' => [
                    'background' => '#ffffff',
                    'lineStyle' => 'solid',
                    'lineHeight' => '1',
                    'lineColor' => "#000000",
                    'paddingTop' => 10
                ]
            ],
            'richText' => [
                'name' => '富文本',
                'type' => 'richText',
                'group' => 'tools',
                'icon' => 'icon-fuwenben',
                'params' => [
                    'content' => '<p>这里是文本的内容</p>'
                ],
                'style' => [
                    'paddingTop' => 0,
                    'paddingLeft' => 0,
                    'background' => '#ffffff'
                ]
            ],
            'service' => [
                'name' => '在线客服',
                'type' => 'service',
                'group' => 'tools',
                'icon' => 'icon-zaixiankefu',
                'params' => [
                    'type' => 'chat',// '客服类型' => chat在线聊天，phone拨打电话，wx小程序客服
                    'image' => self::$base_url . 'image/diy/service.png',
                    'phone_num' => ''
                ],
                'style' => [
                    'right' => '1',
                    'bottom' => '10',
                    'opacity' => '100'
                ]
            ],
            'product' => [
                'name' => '商品组',
                'type' => 'product',
                'group' => 'shop',
                'icon' => 'icon-shangping',
                'params' => [
                    'source' => 'auto', // choice; auto
                    'auto' => [
                        'category' => 0,
                        'productSort' => 'all', // all; sales; price
                        'showNum' => 6,
                        'productName' => 1,
                        'productPrice' => 1,
                    ]
                ],
                'style' => [
                    'background' => '#F6F6F6',
                    'display' => 'list', // list; slide
                    'column' => 2,
                    'show' => [
                        'productName' => 1,
                        'productPrice' => 1,
                        'linePrice' => 1,
                        'sellingPoint' => 0,
                        'productSales' => 0,
                        'paddingTop' => 0,
                        'paddingBottom' => 0,
                        'paddingLeft' => 10,
                    ]
                ],
                // '自动获取' => 默认数据
                'defaultData' => [
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ]
                ],
                // '手动选择' => 默认数据
                'data' => [
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                        'is_default' => true
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                        'is_default' => true
                    ]
                ]
            ],
        ];
        return $data;
    }

    /**
     * 页面diy元素默认数据
     * @return array[]
     */
    public function getDefaultItems()
    {
        return [
            'banner' => [
                'name' => '图片轮播',
                'type' => 'banner',
                'group' => 'media',
                'icon' => 'icon-lunbotu',
                'style' => [
                    'paddingTop' => 0,
                    'paddingBottom' => 0,
                    'paddingLeft' => 0,
                    'topRadio' => 0,
                    'bottomRadio' => 0,
                    'btnColor' => '#ffffff',
                    'background' => '#ffffff',
                    'btnShape' => 'round',//rectangle 长方形，round圆形, square正方形
                    'height' => 340,
                ],
                'data' => [
                    [
                        'imgUrl' => self::$base_url . 'image/diy/banner/01.png',
                        'linkUrl' => ''
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/banner/01.png',
                        'linkUrl' => ''
                    ]
                ]
            ],
            'imageSingle' => [
                'name' => '单图组',
                'type' => 'imageSingle',
                'group' => 'media',
                'icon' => 'icon-tupian111',
                'style' => [
                    'paddingTop' => 0,
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'background' => '#F2F2F2',
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                ],
                'data' => [
                    [
                        'imgUrl' => self::$base_url . 'image/diy/banner/01.png',
                        'imgName' => 'image-1.jpg',
                        'linkUrl' => ''
                    ]
                ]
            ],
            'navBar' => [
                'name' => '导航组',
                'type' => 'navBar',
                'group' => 'media',
                'icon' => 'icon-mulu',
                'style' => [
                    'background' => '#ffffff',
                    'rowsNum' => 4,
                    "bgcolor" => "#f2f2f2",
                    "paddingTop" => 10,
                    "paddingBottom" => 10,
                    "paddingLeft" => 10,
                    "topRadio" => 5,
                    "bottomRadio" => 5
                ],
                'data' => [
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/01.png',
                        'imgName' => 'icon-1.png',
                        'linkUrl' => '',
                        'text' => '按钮文字1',
                        'color' => '#666666'
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/02.png',
                        'imgName' => 'icon-2.jpg',
                        'linkUrl' => '',
                        'text' => '按钮文字2',
                        'color' => '#666666'
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/03.png',
                        'imgName' => 'icon-3.jpg',
                        'linkUrl' => '',
                        'text' => '按钮文字3',
                        'color' => '#666666'
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/navbar/04.png',
                        'imgName' => 'icon-4.jpg',
                        'linkUrl' => '',
                        'text' => '按钮文字4',
                        'color' => '#666666'
                    ]
                ]
            ],
            'blank' => [
                'name' => '辅助空白',
                'type' => 'blank',
                'group' => 'tools',
                'icon' => 'icon-kongbaiye',
                'style' => [
                    'height' => '',
                    'paddingTop' => '',
                    'paddingBottom' => '',
                    'paddingLeft' => '',
                    'topRadio' => '',
                    'bottomRadio' => '',
                    'bgcolor' => '',
                    'background' => '',
                ]
            ],
            'guide' => [
                'name' => '辅助线',
                'type' => 'guide',
                'group' => 'tools',
                'icon' => 'icon-fuzhuxian',
                'style' => [
                    'background' => '#f2f2f2',
                    'lineStyle' => 'solid',
                    'lineHeight' => 1,
                    'lineColor' => "#eeeeee",
                    'paddingTop' => 10,
                    'paddingLeft' => 10,
                    'paddingBottom' => 0,
                ]
            ],
            'video' => [
                'name' => '视频组',
                'type' => 'video',
                'group' => 'media',
                'icon' => 'icon-shipin',
                'params' => [
                    'videoUrl' => 'http://wxsnsdy.tc.qq.com/105/20210/snsdyvideodownload?filekey=30280201010421301f0201690402534804102ca905ce620b1241b726bc41dcff44e00204012882540400',
                    'poster' => self::$base_url . 'image/diy/video_poster.png',
                    'autoplay' => 0
                ],
                'style' => [
                    'paddingTop' => 10,
                    'height' => 190,
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '#f2f2f2',
                ]
            ],
            'article' => [
                'name' => '文章组',
                'type' => 'article',
                'group' => 'media',
                'icon' => 'icon-wenzhangguanli',
                'params' => [
                    'source' => 'auto', // choice; auto
                    'auto' => [
                        'category' => 0,
                        'showNum' => 2
                    ],
                ],
                'style' => [
                    'display' => 10,
                    'background' => '#FFFFFF',
                    'bgcolor' => '#F2F2F2',
                    'paddingTop' => '',
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                ],
                // '自动获取' => 默认数据
                'defaultData' => [
                    [
                        'article_title' => '此处显示文章标题',
                        'show_type' => 10,
                        'image' => self::$base_url . 'image/diy/article/01.png',
                        'views_num' => 309
                    ],
                    [
                        'article_title' => '此处显示文章标题',
                        'show_type' => 10,
                        'image' => self::$base_url . 'image/diy/article/01.png',
                        'views_num' => 309
                    ]
                ],
                // '手动选择' => 默认数据
                'data' => []
            ],
            'special' => [
                'name' => '头条快报',
                'type' => 'special',
                'group' => 'media',
                'icon' => 'icon-gonggao',
                'params' => [
                    'source' => 'auto', // choice; auto
                    'auto' => [
                        'category' => 0,
                        'showNum' => 6
                    ]
                ],
                'style' => [
                    'display' => 1,
                    'image' => self::$base_url . 'image/diy/special.png',
                    'background' => '#ffffff',
                    'bgcolor' => '#f2f2f2',
                    'paddingTop' => '',
                    'paddingBottom' => '',
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5
                ],
                // '自动获取' => 默认数据
                'defaultData' => [
                    [
                        'article_title' => '此处显示头条快报标题'
                    ]
                ],
                // '手动选择' => 默认数据
                'data' => []
            ],
            'notice' => [
                'name' => '公告组',
                'type' => 'notice',
                'group' => 'media',
                'icon' => 'icon-gonggao1',
                'params' => [
                    'text' => '这里是第一条自定义公告的标题',
                    'icon' => self::$base_url . 'image/diy/notice.png'
                ],
                'style' => [
                    'padding' => 4,
                    'paddingTop' => 0,
                    'paddingBottom' => 0,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '',
                    'background' => '#ffffff',
                    'textColor' => '#000000',
                ]
            ],
            'richText' => [
                'name' => '富文本',
                'type' => 'richText',
                'group' => 'tools',
                'icon' => 'icon-fuwenben',
                'params' => [
                    'content' => '<p>这里是文本的内容</p>'
                ],
                'style' => [
                    'paddingTop' => 10,
                    'paddingLeft' => 10,
                    'background' => '#ffffff'
                ]
            ],
            'window' => [
                'name' => '图片橱窗',
                'type' => 'window',
                'group' => 'media',
                'icon' => 'icon-tupian11',
                'style' => [
                    'paddingTop' => 0,
                    'paddingLeft' => 10,
                    'paddingBottom' => 10,
                    'background' => '#f2f2f2',
                    'layout' => 4
                ],
                'data' => [
                    [
                        'imgUrl' => self::$base_url . 'image/diy/window/01.jpg',
                        'linkUrl' => ''
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/window/02.jpg',
                        'linkUrl' => ''
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/window/03.jpg',
                        'linkUrl' => ''
                    ],
                    [
                        'imgUrl' => self::$base_url . 'image/diy/window/04.jpg',
                        'linkUrl' => ''
                    ]
                ],
                'dataNum' => 4
            ],
            'product' => [
                'name' => '商品组',
                'type' => 'product',
                'group' => 'shop',
                'icon' => 'icon-shangping',
                'params' => [
                    'source' => 'auto', // choice; auto
                    'auto' => [
                        'category' => 0,
                        'productSort' => 'all', // all; sales; price
                        'showNum' => 6
                    ],
                    'column' => 2,
                    'display' => 'list',
                    'productName' => 1,
                    'productPrice' => 1,
                    'linePrice' => 1,
                ],
                'style' => [
                    'paddingTop' => '',
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '#F2f2f2',
                    'background' => '#Ffffff',
                    'display' => 'list', // list; slide
                    'column' => 2,
                    'product_name_color' => '#333333',
                    'product_price_color' => '#FF4C01',
                    'line_price_color' => '#999999',
                    'show' => [
                        'productName' => 1,
                        'productPrice' => 1,
                        'linePrice' => 1,
                        'sellingPoint' => 0,
                        'productSales' => 0,
                    ],
                ],
                // '自动获取' => 默认数据
                'defaultData' => [
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                    ]
                ],
                // '手动选择' => 默认数据
                'data' => [
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                        'is_default' => true
                    ],
                    [
                        'product_name' => '此处显示商品名称',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '99.00',
                        'line_price' => '139.00',
                        'selling_point' => '此款商品美观大方 不容错过',
                        'product_sales' => '100',
                        'is_default' => true
                    ]
                ]
            ],
            'coupon' => [
                'name' => '优惠券组',
                'type' => 'coupon',
                'group' => 'shop',
                'icon' => 'icon-hongbao',
                'style' => [
                    'paddingTop' => 10,
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '#f2f2f2',
                    'background' => '#ff4c01',
                    'descolor' => '#666666',
                    'pricecolor' => '#ff4c01',
                    'cillcolor' => '#ff4c01',
                    'btncolor' => '#ff4c01',
                    'btnTxtcolor' => '#FFFFFF',
                    'btnRadio' => 24,
                    'bgtype' => 2,
                    'bgimage' => self::$base_url . 'image/diy/active/coupon.png',
                ],
                'params' => [
                    'btntext' => '立即领取',
                    'limit' => 5
                ],
                'data' => [
                    [
                        'color' => 'red',
                        'reduce_price' => '10',
                        'min_price' => '100.00'
                    ],
                    [
                        'color' => 'violet',
                        'reduce_price' => '10',
                        'min_price' => '100.00'
                    ]
                ]
            ],
            'assembleProduct' => [
                'name' => '拼团商品组',
                'type' => 'assembleProduct',
                'group' => 'shop',
                'icon' => 'icon-pintuangou',
                'params' => [
                    'showNum' => 3,
                    'title' => '标题',
                    'more' => '更多',
                    'btntext' => '去开团'
                ],
                'style' => [
                    'paddingTop' => '',
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '#f2f2f2',
                    'background' => '',
                    'titleType' => 2,
                    'moreSize' => 12,
                    'moreColor' => '#fff',
                    'product_name' => 1,
                    'product_price' => 1,
                    'product_lineprice' => 1,
                    'product_btn' => 1,
                    'product_numberbtn' => 1,
                    'productLine_btnBackground' => '#ff4c01',
                    'productLine_btnRadius' => 30,
                    'title_color1' => '#fc4528',
                    'title_color2' => '#fc7639',
                    'number_color' => '#FFFFFF',
                    'title_image' => self::$base_url . 'image/diy/active/assemble.png',
                    'bgimage' => self::$base_url . 'image/diy/active/assemble_bgimage.png',
                    'product_imgRadio' => 0,
                    'product_topRadio' => 5,
                    'product_bottomRadio' => 5,
                    'productName_color' => '#333333',
                    'productPrice_color' => '#ff4c01',
                    'productBg_color' => '#ffffff',
                    'titleColor' => '#333333',
                    'titleSize' => 14,
                    "productLine_btnColor" => "#ffffff",
                    "productLine_color" => "#999999",
                ],
                // '自动获取' => 默认数据
                'defaultData' => [
                    [
                        'product_name' => '此处是拼团商品',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'selling_point' => '此款商品美观大方 性价比较高 不容错过',
                        'assemble_price' => '99.00',
                        'line_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是拼团商品',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'selling_point' => '此款商品美观大方 性价比较高 不容错过',
                        'assemble_price' => '99.00',
                        'line_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是拼团商品',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'selling_point' => '此款商品美观大方 性价比较高 不容错过',
                        'assemble_price' => '99.00',
                        'line_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是拼团商品',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'selling_point' => '此款商品美观大方 性价比较高 不容错过',
                        'assemble_price' => '99.00',
                        'line_price' => '139.00',
                    ]
                ],
                // '手动选择' => 默认数据
                'data' => [
                    [
                        'product_name' => '此处是拼团商品',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'selling_point' => '此款商品美观大方 性价比较高 不容错过',
                        'assemble_price' => '99.00',
                        'line_price' => '139.00',
                        'is_default' => true
                    ],
                    [
                        'product_name' => '此处是拼团商品',
                        'image' => self::$base_url . 'image/diy/product/01.png',
                        'selling_point' => '此款商品美观大方 性价比较高 不容错过',
                        'assemble_price' => '99.00',
                        'line_price' => '139.00',
                        'is_default' => true
                    ]
                ]
            ],
            'bargainProduct' => [
                'name' => '砍价商品组',
                'type' => 'bargainProduct',
                'group' => 'shop',
                'icon' => 'icon-kanjia1',
                'params' => [
                    'showNum' => 4,
                    'title' => '标题',
                    'more' => '更多',
                ],
                'style' => [
                    'paddingTop' => '',
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '#f2f2f2',
                    'background' => '#ffffff',
                    'titleType' => 2,
                    'title_image' => self::$base_url . 'image/diy/active/bargain.png',
                    'bgimage' => self::$base_url . 'image/diy/active/bargain_bgimage.png',
                    'moreSize' => 12,
                    'moreColor' => '#ffffff',
                    'product_name' => 1,
                    'product_price' => 1,
                    'product_lineprice' => 1,
                    'product_btn' => 1,
                    'product_numberbtn' => 1,
                    'productLine_btnBackground' => '',
                    'productLine_btnRadius' => '',
                    'product_sales' => 1,
                    'title_color1' => '',
                    'title_color2' => '',
                    'number_color' => '',
                    'product_imgRadio' => 5,
                    'productBg_color' => '#ffffff',
                    'product_topRadio' => 5,
                    'product_bottomRadio' => 5,
                    'productName_color' => '#333333',
                    'productPrice_color' => '#ff4c01',
                    'titleColor' => '#333333',
                    'titleSize' => '14',
                    'total_sales' => 1,
                    'salesColor' => '#ffffff',
                    'bgSales' => '#ff6417'
                ],
                // '自动获取' => 默认数据
                'defaultData' => [
                    [
                        'product_name' => '此处是砍价商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'floor_price' => '0.01',
                        'original_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是砍价商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'floor_price' => '0.01',
                        'original_price' => '139.00',
                    ],
                ],
                // '手动选择' => 默认数据
                'data' => [
                    [
                        'product_name' => '此处是砍价商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'floor_price' => '0.01',
                        'original_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是砍价商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'floor_price' => '0.01',
                        'original_price' => '139.00',
                    ],
                ]
            ],
            'seckillProduct' => [
                'name' => '秒杀商品组',
                'type' => 'seckillProduct',
                'group' => 'shop',
                'icon' => 'icon-miaosha11',
                'params' => [
                    'showNum' => 3,
                    'title' => '限时秒杀',
                    'more' => '更多',
                    'btntext' => "去抢购"
                ],
                'style' => [
                    'paddingTop' => '',
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '#f2f2f2',
                    'background' => '#ffffff',
                    'titleType' => 2,
                    'title_image' => self::$base_url . 'image/diy/active/seckill.png',
                    'bgimage' => self::$base_url . 'image/diy/active/seckill_bgimage.png',
                    'moreSize' => 12,
                    'moreColor' => '#FFFFFF',
                    'product_name' => 1,
                    'product_price' => 1,
                    'product_lineprice' => 1,
                    'product_imgRadio' => 5,
                    'productBg_color' => '#ffffff',
                    'product_topRadio' => 0,
                    'product_bottomRadio' => '',
                    'productName_color' => '#333',
                    'productPrice_color' => '#ff4c01',
                    'titleColor' => '#333333',
                    'titleSize' => '14',
                    "product_schedule" => 1,
                    "product_btn" => 1,
                    "title_color1" => "#ffffff",
                    "number_color" => "#ff4c01",
                    "productLine_color" => "#999999",
                    "productLine_btnBackground" => "#ff4c01",
                    "productLine_btnColor" => "#ffffff",
                    "productLine_btnRadius" => 30,
                    "productSlider_color" => "#ff4c01"
                ],
                // '手动选择' => 默认数据
                'data' => [
                    [
                        'product_name' => '此处是秒杀商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'seckill_price' => '69.00',
                        'original_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是秒杀商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'seckill_price' => '69.00',
                        'original_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是秒杀商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'seckill_price' => '69.00',
                        'original_price' => '139.00',
                    ],
                ]
            ],
            'previewProduct' => [
                'name' => '预告商品组',
                'type' => 'previewProduct',
                'group' => 'shop',
                'icon' => 'icon-yushoucuifu',
                'params' => [
                    'showNum' => 1
                ],
                'style' => [
                    'paddingTop' => '',
                    'paddingBottom' => 10,
                    'paddingLeft' => 10,
                    'topRadio' => 5,
                    'bottomRadio' => 5,
                    'bgcolor' => '#f2f2f2',
                    'background' => '#ffffff',
                    'titleType' => 2,
                    'title_image' => self::$base_url . 'image/diy/active/preview.png',
                    'bgimage' => self::$base_url . 'image/diy/active/preview_bg.png',
                    'moreSize' => 12,
                    'moreColor' => '#ffffff',
                    'product_name' => 1,
                    'product_price' => 1,
                    'product_lineprice' => 1,
                    'product_btn' => 1,
                    'product_numberbtn' => 1,
                    'productLine_btnBackground' => '',
                    'productLine_btnRadius' => '',
                    'product_tag' => 1,
                    'title_color1' => '',
                    'title_color2' => '',
                    'number_color' => '',
                    'product_imgRadio' => 5,
                    'productBg_color' => '#ffffff',
                    'product_topRadio' => 5,
                    'product_bottomRadio' => 5,
                    'productName_color' => '#333333',
                    'productPrice_color' => '#ff4c01',
                    'titleColor' => '#333333',
                    'titleSize' => '14',
                    'total_sales' => 1,
                    'tagColor' => '#ffffff',
                    'bgTag' => '#ff6417'
                ],
                // 默认数据
                'data' => [
                    [
                        'product_name' => '此处是预告商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '69.00',
                        'original_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是预告商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '69.00',
                        'original_price' => '139.00',
                    ],
                    [
                        'product_name' => '此处是预告商品',
                        'product_image' => self::$base_url . 'image/diy/product/01.png',
                        'product_price' => '69.00',
                        'original_price' => '139.00',
                    ],
                ]
            ],
            'live' => [
                'name' => 'APP直播',
                'type' => 'live',
                'group' => 'shop',
                'icon' => 'icon-zhibo1',
                'params' => [
                    'source' => 'auto', // choice; auto
                    'showNum' => 6
                ],
                'style' => [
                    'background_image' => self::$base_url . 'image/diy/active/live.png',
                    'color' => '#000000'
                ],
                // '自动获取' => 默认数据
                'defaultData' => [
                    [
                        'shop_name' => '直播间名称',
                        'logo_image' => self::$base_url . 'image/diy/circular.png',
                        'name' => '主播昵称',
                    ],
                    [
                        'shop_name' => '直播间名称',
                        'logo_image' => self::$base_url . 'image/diy/circular.png',
                        'name' => '主播昵称',
                    ],
                ],
                // '手动选择' => 默认数据
                'data' => [
                    [
                        'name' => '直播间名称',
                        'logo_image' => self::$base_url . 'image/diy/circular.png',
                        'anchor_name' => '主播昵称',
                    ],
                    [
                        'name' => '直播间名称',
                        'logo_image' => self::$base_url . 'image/diy/circular.png',
                        'anchor_name' => '主播昵称',
                    ],
                ]
            ],
            'service' => [
                'name' => '在线客服',
                'type' => 'service',
                'group' => 'tools',
                'icon' => 'icon-zaixiankefu',
                'params' => [
                    'type' => 'chat',     // '客服类型' => chat在线聊天，phone拨打电话，wx小程序客服
                    'image' => self::$base_url . 'image/diy/service.png',
                    'phone_num' => ''
                ],
                'style' => [
                    'right' => 1,
                    'bottom' => 10,
                    'opacity' => 100
                ]
            ],
            'title' => [
                'name' => '标题',
                'type' => 'title',
                'group' => 'media',
                'icon' => 'icon-biaoti',
                'style' => [
                    'paddingTop' => 0,
                    'paddingBottom' => 0,
                    'paddingLeft' => 0,
                    'topRadio' => 0,
                    'bottomRadio' => 0,
                    'bgcolor' => '#FFFFFF',
                    'textSize' => 20,
                    'weight' => 800,
                    'isLine' => 1,
                    'lineColor' => '#ff4c01',
                    'isSub' => 1,
                    'subtextSize' => 14,
                    'subtextColor' => '#DDDDDD',
                    'subbackground' => '#FFCCCC',
                    'isMore' => 1,
                    'moretextColor' => '#FF0000',
                    'background' => '#F5F5F5',
                    'textColor' => '#ff4c01',
                    'type' => '1'
                ],
                'params' => [
                    'title' => '标题名称',
                    'subtitle' => '副标题名称',
                    'moretitle' => '更多',
                    'show_icon' => 'yes',
                    'icon' => '',
                    'linkUrl' => '',
                    'sublinkUrl' => ''
                ]
            ],
            'videoLive' => [
                'name' => '视频号直播',
                'type' => 'videoLive',
                'group' => 'tools',
                'icon' => 'icon-shipinbofang',
                'style' => [
                    'right' => 1,
                    'bottom' => 60,
                    'opacity' => 100,
                ],
                'params' => [
                    'finderUserName' => '',
                    'image' => self::$base_url . 'image/diy/videoLive.png',
                ],
            ]
        ];
    }

    /**
     * 格式化页面数据
     * @param $json
     * @return mixed
     */
    public function getPageDataAttr($json)
    {
        // 旧版数据转义
        $array = $this->_transferToNewData($json);
        // 合并默认数据
        return $this->_mergeDefaultData($array);
    }

    /**
     * 自动转换data为json格式
     * @param $value
     * @return false|string
     */
    public function setPageDataAttr($value)
    {
        return json_encode($value ?: ['items' => []]);
    }

    /**
     * diy页面详情
     */
    public static function detail($page_id)
    {
        return (new static())->find($page_id);
    }

    /**
     * diy页面详情
     */
    public static function getHomePage()
    {
        return (new static())->where('page_type', '10')->find();
    }

    /**
     * 旧版数据转义为新版格式
     */
    private function _transferToNewData($json)
    {
        $array = json_decode($json, true);
        $items = $array['items'];
        if (isset($items['page'])) {
            unset($items['page']);
        }
        foreach ($items as &$item) {
            isset($item['data']) && $item['data'] = array_values($item['data']);
        }
        return [
            'page' => isset($array['page']) ? $array['page'] : $array['items']['page'],
            'items' => array_values(array_filter($items))
        ];
    }

    /**
     * 合并默认数据
     */
    private function _mergeDefaultData($array)
    {
        $array['page'] = array_merge_multiple($this->getDefaultPage(), $array['page']);
        $defaultItems = $this->getDefaultItems();
        foreach ($array['items'] as &$item) {
            if (isset($defaultItems[$item['type']])) {
                array_key_exists('data', $item) && $defaultItems[$item['type']]['data'] = [];
                $item = array_merge_multiple($defaultItems[$item['type']], $item);
            }
        }
        return $array;
    }

    /**
     * 首页默认设置
     */
    public static function getDefault($page_type = 10)
    {
        $detail = (new static())->where('is_delete', 0)
            ->where('page_type', $page_type)
            ->order('is_default desc,page_id desc')
            ->find();
        if (!$detail) {
            self::addDefault($page_type, self::$app_id);
            $detail = (new static())->where('is_delete', 0)
                ->where('page_type', $page_type)
                ->order('is_default desc,page_id desc')
                ->find();
        }
        return $detail;
    }

    /**
     * 添加默认首页和个人中心
     */
    public static function addDefault($page_type, $app_id)
    {
        if ($page_type == 10) {
            $page_data = '{"page":{"type":"page","name":"\u9875\u9762\u8bbe\u7f6e","params":{"name":"\u4f01\u4e1a\u7aef","title":"\u4e09\u52fe\u5546\u57ce","title_type":"text","share_title":"\u5206\u4eab\u6807\u9898","share_img":"https:\/\/multi3.jjjshop.net\/image\/diy\/logo.png","toplogo":"https:\/\/multi3.jjjshop.net\/image\/diy\/logo_top.png","icon":"icon-biaoti"},"style":{"titleTextColor":"#FFFFFF","titleBackgroundColor":"#ff4c01","toplogo":"http:\/\/www.jjj-shop-enterprise.com\/uploads\/06\/550891636926d02f1c65615168c59d.png","toptype":"http:\/\/wx-cdn.jiujiuyunhui.com\/202106181812000d7460037.png","backgroundUrl":"http:\/\/www.bestshop.com\/assets\/store\/img\/diy\/phone-top-black.png"},"category":{"open":1,"color":"#FFFFFF"},"style1":{"titleTextColor":"black","titleBackgroundColor":"#ffffff"},"id":"page","logo_type":"text"},"items":[{"name":"\u5728\u7ebf\u5ba2\u670d","type":"service","group":"tools","icon":"icon-zaixiankefu","params":{"type":"chat","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/service.png","phone_num":"18600000000"},"style":{"right":1,"bottom":10,"opacity":100}},{"name":"\u89c6\u9891\u53f7\u76f4\u64ad","type":"videoLive","group":"tools","icon":"icon-shipinbofang","style":{"right":1,"bottom":60,"opacity":100},"params":{"finderUserName":"sphUnEWXOQtd7bF","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/videoLive.png"}},{"name":"\u56fe\u7247\u8f6e\u64ad","type":"banner","group":"media","icon":"icon-lunbotu","style":{"paddingTop":10,"paddingBottom":10,"paddingLeft":10,"topRadio":8,"bottomRadio":8,"btnColor":"#ffffff","background":"#f2f2f2","btnShape":"round","height":340,"imgShape":"rectangle"},"data":[{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/2023072816230614fe25648.jpg","linkUrl":""},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/202307281623075400b1871.png","linkUrl":""}],"params":{"interval":"2800"}},{"name":"\u516c\u544a\u7ec4","type":"notice","group":"media","icon":"icon-gonggao1","params":{"text":"\u6b22\u8fce\u8fdb\u5165\u4e09\u52fe\u5546\u57ce\uff0c\u5f00\u5fc3\u8d2d\u7269\uff0c\u6ee1\u8f7d\u800c\u5f52\uff01","icon":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/2cbb4d5bc8d74d61b04905a17804b0a7.png"},"style":{"padding":4,"paddingTop":0,"paddingBottom":0,"paddingLeft":10,"topRadio":5,"bottomRadio":5,"bgcolor":"#f2f2f2","background":"#ffffff","textColor":"#333333"}},{"name":"\u5bfc\u822a\u7ec4","type":"navBar","group":"media","icon":"icon-mulu","style":{"background":"#FFFFFF","rowsNum":"5","bgcolor":"#f2f2f2","paddingTop":10,"paddingBottom":0,"paddingLeft":10,"topRadio":8,"bottomRadio":0},"data":[{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728162720f16804027.png","imgName":"icon-1.png","linkUrl":"pagesPlus\/lottery\/lottery","text":"\u8f6c\u76d8\u62bd\u5956","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u5e78\u8fd0\u8f6c\u76d8"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728162720d3df25092.png","imgName":"icon-2.jpg","linkUrl":"pagesPlus\/table\/table?table_id=22","text":"\u4e07\u80fd\u8868\u5355","color":"#666666","name":"\u94fe\u63a5\u5230 \u8868\u5355 \u4e0b\u5355"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/202307281627204ff856390.png","imgName":"icon-3.jpg","linkUrl":"pagesPlus\/points\/list\/list","text":"\u79ef\u5206\u5546\u57ce","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u79ef\u5206\u5546\u57ce"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728162719483612825.png","imgName":"icon-4.jpg","linkUrl":"pages\/coupon\/coupon","text":"\u9886\u5238\u4e2d\u5fc3","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u9886\u5238\u4e2d\u5fc3"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/202307281627195d4894665.png","imgName":"icon-1.png","linkUrl":"\/pagesPlus\/signin\/signin","text":"\u7b7e\u5230\u6709\u793c","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u7b7e\u5230\u6709\u793c"}]},{"name":"\u5bfc\u822a\u7ec4","type":"navBar","group":"media","icon":"icon-mulu","style":{"background":"#FFFFFF","rowsNum":"5","bgcolor":"#f2f2f2","paddingTop":0,"paddingBottom":10,"paddingLeft":10,"topRadio":0,"bottomRadio":8},"data":[{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/2023072816271935c465468.png","imgName":"icon-1.png","linkUrl":"pagesPlus\/presale\/list","text":"\u9884\u552e\u6d3b\u52a8","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u9884\u552e"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728162719014183428.png","imgName":"icon-2.jpg","linkUrl":"pagesPlus\/assemble\/list\/list","text":"\u9650\u65f6\u62fc\u56e2","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u62fc\u56e2"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728162719ae94b7112.png","imgName":"icon-3.jpg","linkUrl":"pagesPlus\/preview\/list","text":"\u9650\u65f6\u79d2\u6740","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u9884\u544a"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728162719659b57838.png","imgName":"icon-4.jpg","linkUrl":"pagesPlus\/bargain\/list\/list","text":"\u9650\u65f6\u780d\u4ef7","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u780d\u4ef7"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728162719b2d952564.png","imgName":"icon-1.png","linkUrl":"pagesPlus\/preview\/list","text":"\u597d\u7269\u9884\u544a","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u9884\u544a"}]},{"name":"\u4f18\u60e0\u5238\u7ec4","type":"coupon","group":"shop","icon":"icon-hongbao","style":{"paddingTop":0,"paddingBottom":10,"paddingLeft":10,"topRadio":8,"bottomRadio":8,"bgcolor":"#f2f2f2","background":"#ffffff","descolor":"#666666","pricecolor":"#ff4c01","cillcolor":"#ff4c01","btncolor":"#ff4c01","btnTxtcolor":"#FFFFFF","btnRadio":24,"bgtype":2,"bgimage":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/coupon.png"},"params":{"btntext":"\u7acb\u5373\u9886\u53d6","limit":5},"data":[{"color":"red","reduce_price":"10","min_price":"100.00"},{"color":"violet","reduce_price":"10","min_price":"100.00"}]},{"name":"\u62fc\u56e2\u5546\u54c1\u7ec4","type":"assembleProduct","group":"shop","icon":"icon-pintuangou","params":{"showNum":6,"title":"\u6807\u9898","more":"\u66f4\u591a","btntext":"\u53bb\u5f00\u56e2","source":"auto","auto":{"category":0,"productSort":"all"}},"style":{"paddingTop":"","paddingBottom":10,"paddingLeft":10,"topRadio":5,"bottomRadio":5,"bgcolor":"#f2f2f2","background":"#ffffff","titleType":2,"moreSize":12,"moreColor":"#fff","product_name":1,"product_price":1,"product_lineprice":1,"product_btn":1,"product_numberbtn":1,"productLine_btnBackground":"#ff4c01","productLine_btnRadius":30,"title_color1":"#fc4528","title_color2":"#fc7639","number_color":"#FFFFFF","title_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/assemble.png","bgimage":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/assemble_bgimage.png","product_imgRadio":0,"product_topRadio":8,"product_bottomRadio":8,"productName_color":"#333333","productPrice_color":"#ff4c01","productBg_color":"#ffffff","titleColor":"#333333","titleSize":14,"productLine_btnColor":"#ffffff","productLine_color":"#999999","color":"#C9C9C9","background_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/assemble.png","column":1,"show":{"productName":true,"sellingPoint":true,"assemblePrice":true,"linePrice":true}},"defaultData":[{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","assemble_price":"99.00","line_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","assemble_price":"99.00","line_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","assemble_price":"99.00","line_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","assemble_price":"99.00","line_price":"139.00"}],"data":[{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","assemble_price":"99.00","line_price":"139.00","is_default":true},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","assemble_price":"99.00","line_price":"139.00","is_default":true}]},{"name":"\u780d\u4ef7\u5546\u54c1\u7ec4","type":"bargainProduct","group":"shop","icon":"icon-kanjia1","params":{"showNum":6,"title":"\u6807\u9898","more":"\u66f4\u591a","source":"auto","auto":{"category":0,"productSort":"all","showNum":6}},"style":{"paddingTop":"","paddingBottom":10,"paddingLeft":10,"topRadio":5,"bottomRadio":5,"bgcolor":"#f2f2f2","background":"#ffffff","titleType":2,"title_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/bargain.png","bgimage":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/bargain_bgimage.png","moreSize":12,"moreColor":"#ffffff","product_name":1,"product_price":1,"product_lineprice":1,"product_btn":1,"product_numberbtn":1,"productLine_btnBackground":"","productLine_btnRadius":"","product_sales":1,"title_color1":"","title_color2":"","number_color":"","product_imgRadio":5,"productBg_color":"#ffffff","product_topRadio":8,"product_bottomRadio":8,"productName_color":"#333333","productPrice_color":"#ff4c01","titleColor":"#333333","titleSize":"14","total_sales":1,"salesColor":"#ffffff","bgSales":"#ff6417","color":"#ffffff","countdown_color":"#FF02A8","countdown_back_color":"#FEE24F","background_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/bargain.png","column":1,"show":{"productName":1,"peoples":1,"floorPrice":1,"originalPrice":1},"productLine_color":"#999999"},"defaultData":[{"product_name":"\u6b64\u5904\u662f\u780d\u4ef7\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","floor_price":"0.01","original_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u780d\u4ef7\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","floor_price":"0.01","original_price":"139.00"}],"data":[{"product_name":"\u6b64\u5904\u662f\u780d\u4ef7\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","floor_price":"0.01","original_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u780d\u4ef7\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","floor_price":"0.01","original_price":"139.00"}],"demo":{"helps_count":2,"helps":[{"avatarUrl":"http:\/\/tva1.sinaimg.cn\/large\/0060lm7Tly1g4c7zrytvvj30dw0dwwes.jpg"},{"avatarUrl":"http:\/\/tva1.sinaimg.cn\/large\/0060lm7Tly1g4c7zs2u5ej30b40b4dfx.jpg"}]}},{"name":"\u79d2\u6740\u5546\u54c1\u7ec4","type":"seckillProduct","group":"shop","icon":"icon-miaosha1","params":{"showNum":6,"title":"\u9650\u65f6\u79d2\u6740","more":"\u66f4\u591a","btntext":"\u53bb\u62a2\u8d2d"},"style":{"paddingTop":"","paddingBottom":10,"paddingLeft":10,"topRadio":5,"bottomRadio":5,"bgcolor":"#f2f2f2","background":"#ffffff","titleType":2,"title_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/seckill.png","bgimage":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/seckill_bgimage.png","moreSize":12,"moreColor":"#FFFFFF","product_name":1,"product_price":1,"product_lineprice":1,"product_imgRadio":8,"productBg_color":"#ffffff","product_topRadio":0,"product_bottomRadio":"","productName_color":"#333","productPrice_color":"#ff4c01","titleColor":"#333333","titleSize":"14","product_schedule":1,"product_btn":1,"title_color1":"#ffffff","number_color":"#ff4c01","productLine_color":"#999999","productLine_btnBackground":"#ff4c01","productLine_btnColor":"#ffffff","productLine_btnRadius":30,"productSlider_color":"#ff4c01","color":"#ffffff","countdown_color":"#FF302F","countdown_back_color":"#FEE250","background_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/seckill.png","column":3,"show":{"productName":true,"seckillPrice":true,"linePrice":true}},"data":[{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","seckill_price":"69.00","original_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","seckill_price":"69.00","original_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","seckill_price":"69.00","original_price":"139.00"}]},{"name":"\u9884\u544a\u5546\u54c1\u7ec4","type":"previewProduct","group":"shop","icon":"icon-yushoucuifu","params":{"showNum":6,"more":"\u66f4\u591a"},"style":{"paddingTop":"","paddingBottom":0,"paddingLeft":10,"topRadio":8,"bottomRadio":8,"bgcolor":"#f2f2f2","background":"#ffffff","titleType":2,"title_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/preview.png","bgimage":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/preview_bg.png","moreSize":12,"moreColor":"#ffffff","product_name":1,"product_price":1,"product_lineprice":1,"product_btn":1,"product_numberbtn":1,"productLine_btnBackground":"","productLine_btnRadius":"","product_tag":1,"title_color1":"","title_color2":"","number_color":"","product_imgRadio":8,"productBg_color":"#ffffff","product_topRadio":8,"product_bottomRadio":8,"productName_color":"#333333","productPrice_color":"#ff4c01","titleColor":"#333333","titleSize":"14","total_sales":1,"tagColor":"#ffffff","bgTag":"#ff4c01","color":"#ff4c01","countdown_color":"#ff4c01","countdown_back_color":"#fef7e4","background_color":"#ff4c01","top_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/preview_top.png","background_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/preview.png","productLine_color":"#999999"},"data":[{"product_name":"\u6b64\u5904\u662f\u9884\u544a\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"69.00","original_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u9884\u544a\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"69.00","original_price":"139.00"},{"product_name":"\u6b64\u5904\u662f\u9884\u544a\u5546\u54c1","product_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"69.00","original_price":"139.00"}]},{"name":"APP\u76f4\u64ad","type":"live","group":"shop","icon":"icon-zhibo1","params":{"source":"auto","showNum":6},"style":{"background_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/active\/live.png","color":"#000000"},"defaultData":[{"shop_name":"\u76f4\u64ad\u95f4\u540d\u79f0","logo_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/circular.png","name":"\u4e3b\u64ad\u6635\u79f0"},{"shop_name":"\u76f4\u64ad\u95f4\u540d\u79f0","logo_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/circular.png","name":"\u4e3b\u64ad\u6635\u79f0"}],"data":[{"name":"\u76f4\u64ad\u95f4\u540d\u79f0","logo_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/circular.png","anchor_name":"\u4e3b\u64ad\u6635\u79f0"},{"name":"\u76f4\u64ad\u95f4\u540d\u79f0","logo_image":"https:\/\/multi3.jjjshop.net\/image\/diy\/circular.png","anchor_name":"\u4e3b\u64ad\u6635\u79f0"}]},{"name":"\u6807\u9898","type":"title","group":"media","icon":"icon-biaoti","style":{"paddingTop":0,"paddingBottom":0,"paddingLeft":0,"topRadio":0,"bottomRadio":0,"bgcolor":"#FFFFFF","textSize":20,"weight":800,"isLine":1,"lineColor":"#ff4c01","isSub":1,"subtextSize":14,"subtextColor":"#DDDDDD","subbackground":"#FFCCCC","isMore":1,"moretextColor":"#FF0000","background":"#F2f2f2","textColor":"#333","type":"1"},"params":{"title":"\u5546\u54c1\u63a8\u8350","subtitle":"\u526f\u6807\u9898\u540d\u79f0","moretitle":"\u66f4\u591a","show_icon":"yes","icon":"","linkUrl":"","sublinkUrl":""}},{"name":"\u5546\u54c1\u7ec4","type":"product","group":"shop","icon":"icon-shangping","params":{"source":"auto","auto":{"category":0,"productSort":"all","showNum":6},"column":2,"display":"list","productName":1,"productPrice":1},"style":{"paddingTop":"","paddingBottom":10,"paddingLeft":10,"topRadio":5,"bottomRadio":5,"bgcolor":"#F2f2f2","background":"#Ffffff","display":"list","column":2,"show":{"productName":1,"productPrice":1,"linePrice":1,"sellingPoint":0,"productSales":0}},"defaultData":[{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"}],"data":[{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100","is_default":true},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100","is_default":true}]},{"name":"\u62fc\u56e2\u5546\u54c1\u7ec4","type":"sharingProduct","params":{"source":"auto","auto":{"category":0,"productSort":"all","showNum":6},"showNum":4},"style":{"background":"#F6F6F6","show":{"productName":1,"sellingPoint":1,"sharingPrice":1,"linePrice":1},"column":2},"defaultData":[{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","sharing_price":99,"line_price":139},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","product_price":99,"line_price":139},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","sharing_price":99,"line_price":139},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","sharing_price":99,"line_price":139}],"data":[{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","sharing_price":99,"line_price":139,"is_default":true},{"product_name":"\u6b64\u5904\u662f\u62fc\u56e2\u5546\u54c1","image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u6027\u4ef7\u6bd4\u8f83\u9ad8 \u4e0d\u5bb9\u9519\u8fc7","sharing_price":99,"line_price":139,"is_default":true}]},{"name":"\u79d2\u6740\u5546\u54c1\u7ec4","type":"sharpProduct","params":{"showNum":6},"style":{"background":"#ffffff","column":3,"show":{"productName":1,"seckillPrice":1,"originalPrice":1}},"data":[{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","seckill_price":69,"original_price":139},{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","seckill_price":69,"original_price":139},{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","seckill_price":69,"original_price":139}]},{"name":"\u79d2\u6740\u5546\u54c1\u7ec4","type":"sharpProduct","params":{"showNum":6},"style":{"background":"#ffffff","column":3,"show":{"productName":1,"seckillPrice":1,"originalPrice":1}},"data":[{"seckill_product_id":117,"product_id":56,"limit_num":0,"stock":3592,"app_id":10001,"create_time":"2020-04-10 11:07:48","update_time":"2020-04-10 11:09:01","seckill_activity_id":7,"total_sales":8,"product":{"product_id":56,"product_name":"\u677e\u4e4b\u9f99\u5b9d\u5b9d\u9632\u6454\u5934\u90e8\u4fdd\u62a4\u57ab\u5a74\u513f\u5b66\u6b65\u9632\u649e\u5e3d\u513f\u7ae5\u5b89\u5168\u5934\u76d4\u62a4\u5934\u6795\u795e\u5668","product_price":234,"product_no":59473184412,"product_socket":0,"selling_point":"\u3010\u963f\u575d\u5dde\u6276\u8d2b\u9986\u3011\u9ad8\u539f\u65b0\u9c9c\u7266\u725b\u8089 \u725b\u8171\u5b50\u540e\u817f\u91cc\u810a\u725b\u8169 \u808b\u6761\u65e0\u9aa8\u725b\u5934 \u725b\u68d2\u9aa8\u725b\u6392\u9aa8 \u725b\u820c\u725b\u5c3e \u540e\u817f1kg","category_id":5,"spec_type":20,"deduct_stock_type":20,"content":"<p><img src=\"http:\/\/wx-cdn.jiujiuyunhui.com\/20191224215458c925a4339.jpg\" width=\"100%\"\/><\/p><p><img src=\"http:\/\/wx-cdn.jiujiuyunhui.com\/201912242154506f7835263.jpg\" width=\"100%\"\/><\/p><p><br\/><\/p>","sales_initial":50,"sales_actual":134,"product_sort":1,"delivery_id":10018,"is_points_gift":1,"is_points_discount":1,"is_enable_grade":1,"is_alone_grade":0,"alone_grade_equity":"","is_ind_agent":0,"agent_money_type":10,"first_money":0,"second_money":0,"third_money":0,"product_status":{"text":"\u4e0a\u67b6","value":10},"is_delete":0,"app_id":10001,"create_time":"2020-04-08 21:04:22","update_time":"2020-04-17 20:15:50","image":[{"id":202,"product_id":56,"image_id":10210,"app_id":10001,"create_time":"2019-12-27 10:55:17","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/20191224215458c925a4339.jpg","file_name":"20191224215458c925a4339.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"},{"id":203,"product_id":56,"image_id":10209,"app_id":10001,"create_time":"2019-12-27 10:55:18","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/201912242154506f7835263.jpg","file_name":"201912242154506f7835263.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"}],"product_sales":184}},{"seckill_product_id":118,"product_id":57,"limit_num":0,"stock":20,"app_id":10001,"create_time":"2020-04-18 19:08:48","update_time":"2020-04-18 19:08:48","seckill_activity_id":7,"total_sales":0,"product":{"product_id":57,"product_name":"SUK \u5ea7\u5c71\u96d5\uff01\u9ed1\u8272\u7fbd\u7ed2\u670d\u5973\u4e2d\u957f\u6b3e\u51ac\u5b63\u65b0\u6b3e\u6ee9\u7f8a\u6bdb\u5bbd\u677e\u5916\u5957\u5973\u52a0\u539a\u6f6e","product_price":912,"product_no":52726307099,"product_socket":12,"selling_point":"\u6d77\u91cf\u65b0\u54c1 \u6f6e\u6d41\u7a7f\u642d \u73a9\u8da3\u4e92\u52a8","category_id":6,"spec_type":10,"deduct_stock_type":20,"content":"<p><img src=\"http:\/\/wx-cdn.jiujiuyunhui.com\/20191225111312882235316.jpg\" width=\"100%\"\/><\/p><p><br\/><\/p>","sales_initial":100,"sales_actual":22,"product_sort":15,"delivery_id":10011,"is_points_gift":1,"is_points_discount":1,"is_enable_grade":1,"is_alone_grade":0,"alone_grade_equity":"","is_ind_agent":0,"agent_money_type":10,"first_money":0,"second_money":0,"third_money":0,"product_status":{"text":"\u4e0a\u67b6","value":10},"is_delete":0,"app_id":10001,"create_time":"2020-04-08 21:04:22","update_time":"2020-04-18 17:16:14","image":[{"id":217,"product_id":57,"image_id":10227,"app_id":10001,"create_time":"2020-01-15 21:08:37","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/20191225111216c76c73345.jpg","file_name":"20191225111216c76c73345.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"},{"id":218,"product_id":57,"image_id":10226,"app_id":10001,"create_time":"2020-01-15 21:08:37","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/2019122511121533fc81582.jpg","file_name":"2019122511121533fc81582.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"}],"product_sales":122}}]},{"name":"\u79d2\u6740\u5546\u54c1\u7ec4","type":"sharpProduct","params":{"showNum":6},"style":{"background":"#ffffff","column":3,"show":{"productName":1,"seckillPrice":1,"originalPrice":1}},"data":[{"seckill_product_id":117,"product_id":56,"limit_num":0,"stock":3592,"app_id":10001,"create_time":"2020-04-10 11:07:48","update_time":"2020-04-10 11:09:01","seckill_activity_id":7,"total_sales":8,"product":{"product_id":56,"product_name":"\u677e\u4e4b\u9f99\u5b9d\u5b9d\u9632\u6454\u5934\u90e8\u4fdd\u62a4\u57ab\u5a74\u513f\u5b66\u6b65\u9632\u649e\u5e3d\u513f\u7ae5\u5b89\u5168\u5934\u76d4\u62a4\u5934\u6795\u795e\u5668","product_price":234,"product_no":59473184412,"product_socket":0,"selling_point":"\u3010\u963f\u575d\u5dde\u6276\u8d2b\u9986\u3011\u9ad8\u539f\u65b0\u9c9c\u7266\u725b\u8089 \u725b\u8171\u5b50\u540e\u817f\u91cc\u810a\u725b\u8169 \u808b\u6761\u65e0\u9aa8\u725b\u5934 \u725b\u68d2\u9aa8\u725b\u6392\u9aa8 \u725b\u820c\u725b\u5c3e \u540e\u817f1kg","category_id":5,"spec_type":20,"deduct_stock_type":20,"content":"<p><img src=\"http:\/\/wx-cdn.jiujiuyunhui.com\/20191224215458c925a4339.jpg\" width=\"100%\"\/><\/p><p><img src=\"http:\/\/wx-cdn.jiujiuyunhui.com\/201912242154506f7835263.jpg\" width=\"100%\"\/><\/p><p><br\/><\/p>","sales_initial":50,"sales_actual":134,"product_sort":1,"delivery_id":10018,"is_points_gift":1,"is_points_discount":1,"is_enable_grade":1,"is_alone_grade":0,"alone_grade_equity":"","is_ind_agent":0,"agent_money_type":10,"first_money":0,"second_money":0,"third_money":0,"product_status":{"text":"\u4e0a\u67b6","value":10},"is_delete":0,"app_id":10001,"create_time":"2020-04-08 21:04:22","update_time":"2020-04-17 20:15:50","image":[{"id":202,"product_id":56,"image_id":10210,"app_id":10001,"create_time":"2019-12-27 10:55:17","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/20191224215458c925a4339.jpg","file_name":"20191224215458c925a4339.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"},{"id":203,"product_id":56,"image_id":10209,"app_id":10001,"create_time":"2019-12-27 10:55:18","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/201912242154506f7835263.jpg","file_name":"201912242154506f7835263.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"}],"product_sales":184}},{"seckill_product_id":118,"product_id":57,"limit_num":0,"stock":20,"app_id":10001,"create_time":"2020-04-18 19:08:48","update_time":"2020-04-18 19:08:48","seckill_activity_id":7,"total_sales":0,"product":{"product_id":57,"product_name":"SUK \u5ea7\u5c71\u96d5\uff01\u9ed1\u8272\u7fbd\u7ed2\u670d\u5973\u4e2d\u957f\u6b3e\u51ac\u5b63\u65b0\u6b3e\u6ee9\u7f8a\u6bdb\u5bbd\u677e\u5916\u5957\u5973\u52a0\u539a\u6f6e","product_price":912,"product_no":52726307099,"product_socket":12,"selling_point":"\u6d77\u91cf\u65b0\u54c1 \u6f6e\u6d41\u7a7f\u642d \u73a9\u8da3\u4e92\u52a8","category_id":6,"spec_type":10,"deduct_stock_type":20,"content":"<p><img src=\"http:\/\/wx-cdn.jiujiuyunhui.com\/20191225111312882235316.jpg\" width=\"100%\"\/><\/p><p><br\/><\/p>","sales_initial":100,"sales_actual":22,"product_sort":15,"delivery_id":10011,"is_points_gift":1,"is_points_discount":1,"is_enable_grade":1,"is_alone_grade":0,"alone_grade_equity":"","is_ind_agent":0,"agent_money_type":10,"first_money":0,"second_money":0,"third_money":0,"product_status":{"text":"\u4e0a\u67b6","value":10},"is_delete":0,"app_id":10001,"create_time":"2020-04-08 21:04:22","update_time":"2020-04-18 17:16:14","image":[{"id":217,"product_id":57,"image_id":10227,"app_id":10001,"create_time":"2020-01-15 21:08:37","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/20191225111216c76c73345.jpg","file_name":"20191225111216c76c73345.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"},{"id":218,"product_id":57,"image_id":10226,"app_id":10001,"create_time":"2020-01-15 21:08:37","file_path":"http:\/\/wx-cdn.jiujiuyunhui.com\/2019122511121533fc81582.jpg","file_name":"2019122511121533fc81582.jpg","file_url":"http:\/\/wx-cdn.jiujiuyunhui.com"}],"product_sales":122}}]},{"name":"\u79d2\u6740\u5546\u54c1\u7ec4","type":"sharpProduct","params":{"showNum":6},"style":{"background":"#ffffff","column":3,"show":{"productName":1,"seckillPrice":1,"originalPrice":1}},"data":[{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","seckill_price":69,"original_price":139},{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","seckill_price":69,"original_price":139},{"product_name":"\u6b64\u5904\u662f\u79d2\u6740\u5546\u54c1","product_image":"http:\/\/www.jjj-shop.com\/image\/diy\/product\/01.png","seckill_price":69,"original_price":139}]}]}';
            $data = [
                'page_type' => 10,
                'page_name' => '首页装修',
                'page_data' => json_decode($page_data, 1),
                'is_default' => 1,
                'app_id' => $app_id
            ];
            return (new static())->save($data);
        } elseif ($page_type == 30) {
            $page_data = '{"page":{"type":"page","name":"\u9875\u9762\u8bbe\u7f6e","params":{"name":"\u4e2a\u4eba\u4e2d\u5fc3","title":"\u9875\u9762\u6807\u9898","title_type":"text","share_title":"\u5206\u4eab\u6807\u9898","share_img":"https:\/\/multi3.jjjshop.net\/image\/diy\/logo.png","toplogo":"https:\/\/multi3.jjjshop.net\/image\/diy\/logo_top.png","icon":"icon-biaoti"},"style":{"titleTextColor":"black","titleBackgroundColor":"#ffffff"},"category":{"open":0,"color":"#000000"}},"items":[{"name":"\u57fa\u7840\u4fe1\u606f","type":"base","group":"page","icon":"icon-jibenxinxi","style":{"background":"#ffffff","padding":48,"paddingTop":0,"paddingBottom":0,"paddingLeft":0,"bgcolor":"#f2f2f2","type":1}},{"name":"\u6807\u9898","type":"title","group":"media","icon":"icon-biaoti","style":{"paddingTop":10,"paddingBottom":0,"paddingLeft":10,"topRadio":8,"bottomRadio":0,"bgcolor":"#f2f2f2","textSize":16,"weight":800,"isLine":0,"lineColor":"#FF5500","isSub":0,"subtextSize":14,"subtextColor":"#999999","subbackground":"#ffffff","isMore":1,"moretextColor":"#999999","background":"#ffffff","textColor":"#333","type":8},"params":{"title":"\u6211\u7684\u8ba2\u5355","subtitle":"\u526f\u6807\u9898\u540d\u79f0","moretitle":"\u66f4\u591a","show_icon":"yes","icon":"","linkUrl":"","sublinkUrl":"","morelinkUrl":"\/pages\/order\/myorder"}},{"name":"\u6211\u7684\u8ba2\u5355","type":"order","group":"page","icon":"icon-wodedingdan","style":{"background":"#ffffff","paddingTop":0,"paddingBottom":0,"paddingLeft":10,"bgcolor":"#f2f2f2","topRadio":0,"bottomRadio":8,"type":1}},{"name":"\u6807\u9898","type":"title","group":"media","icon":"icon-biaoti","style":{"paddingTop":10,"paddingBottom":0,"paddingLeft":10,"topRadio":10,"bottomRadio":0,"bgcolor":"#f2f2f2","textSize":16,"weight":800,"isLine":0,"lineColor":"#FF5500","isSub":0,"subtextSize":14,"subtextColor":"#FF5500","subbackground":"#FFCCCC","isMore":1,"moretextColor":"#999","background":"#FFFFFF","textColor":"#333","type":8},"params":{"title":"\u6211\u7684\u670d\u52a1","subtitle":"\u526f\u6807\u9898\u540d\u79f0","moretitle":"","show_icon":"yes","icon":"","linkUrl":"","sublinkUrl":""}},{"name":"\u5bfc\u822a\u7ec4","type":"navBar","group":"media","icon":"icon-mulu","style":{"background":"#ffffff","rowsNum":5,"bgcolor":"#f2f2f2","paddingTop":0,"paddingBottom":0,"paddingLeft":10,"topRadio":0,"bottomRadio":8},"data":[{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/202307281635530bb015469.png","imgName":"icon-2.jpg","linkUrl":"\/pages\/user\/address\/address","text":"\u6536\u8d27\u5730\u5740","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6536\u8d27\u5730\u5740"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/faac2c4d492877abb92317747531e31c.png","imgName":"icon-1.png","linkUrl":"pagesPlus\/lottery\/lottery","text":"\u8f6c\u76d8\u62bd\u5956","color":"#666666","name":"\u94fe\u63a5\u5230 \u8425\u9500 \u5e78\u8fd0\u8f6c\u76d8"},{"imgUrl":"http:\/\/qn-cdn.jjjshop.net\/202303220906070e1de0550.png","imgName":"icon-3.jpg","linkUrl":"\/pages\/coupon\/coupon","text":"\u9886\u5238\u4e2d\u5fc3","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u9886\u5238\u4e2d\u5fc3"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/4e35e89b0a6de5d84b9953614da06af1.png","imgName":"icon-4.jpg","linkUrl":"\/pages\/user\/my-coupon\/my-coupon","text":"\u6211\u7684\u4f18\u60e0\u5238","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6211\u7684\u4f18\u60e0\u5238"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/e2e2d24e93ab27b99893e36388299feb.png","imgName":"icon-1.png","linkUrl":"\/pages\/agent\/index\/index","text":"\u5206\u9500\u4e2d\u5fc3","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u5206\u9500\u4e2d\u5fc3"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728163539dfa889883.png","imgName":"icon-1.png","linkUrl":"\/pages\/user\/my-bargain\/my-bargain","text":"\u6211\u7684\u780d\u4ef7","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6211\u7684\u780d\u4ef7"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/202307281635400da7e4029.png","imgName":"icon-1.png","linkUrl":"\/pages\/user\/my_attention\/my_attention","text":"\u6211\u7684\u6536\u85cf","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6211\u7684\u6536\u85cf"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/202307281635391aa185765.png","imgName":"icon-1.png","linkUrl":"\/pagesPlus\/signin\/signin","text":"\u7b7e\u5230\u6709\u793c","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u7b7e\u5230\u6709\u793c"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/25b9462915515ae7509087d872149104.png","imgName":"icon-1.png","linkUrl":"\/pages\/user\/my_collect\/my_collect","text":"\u6211\u7684\u5173\u6ce8","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6211\u7684\u5173\u6ce8"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/20230728163539cd2582888.png","imgName":"icon-1.png","linkUrl":"\/pages\/user\/set\/set","text":"\u8bbe\u7f6e","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u8bbe\u7f6e"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/077f577128448fcb845404e151b2d842.png","imgName":"icon-1.png","linkUrl":"\/pages\/order\/assemble-order","text":"\u6211\u7684\u62fc\u56e2","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6211\u7684\u62fc\u56e2"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/82a455da78726cf8294026215169f58b.png","imgName":"icon-1.png","linkUrl":"\/pages\/shop\/index","text":"\u5546\u6237\u7ba1\u7406","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u5546\u6237\u7ba1\u7406"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/83ec3f6f1ffc92691fd4de7cfd2ee176.png","imgName":"icon-1.png","linkUrl":"\/pagesPlus\/task\/index","text":"\u4efb\u52a1\u4e2d\u5fc3","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u4efb\u52a1\u4e2d\u5fc3"},{"imgUrl":"https:\/\/multi3.jjjshop.net\/uploads\/20231103\/ad28d664c96e702bb0a9824d6432c360.png","imgName":"icon-1.png","linkUrl":"\/pages\/user\/evaluate\/list","text":"\u6211\u7684\u8bc4\u4ef7","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6211\u7684\u8bc4\u4ef7"},{"imgUrl":"https:\/\/qn-cdn.jjjshop.net\/202311101130176f2d59784.png","imgName":"icon-1.png","linkUrl":"\/pagesPlus\/chat\/notice_list","text":"\u6211\u7684\u6d88\u606f","color":"#666666","name":"\u94fe\u63a5\u5230 \u83dc\u5355 \u6211\u7684\u6d88\u606f"}]},{"name":"\u6807\u9898","type":"title","group":"media","icon":"icon-biaoti","style":{"paddingTop":0,"paddingBottom":0,"paddingLeft":0,"topRadio":0,"bottomRadio":0,"bgcolor":"#F2f2f2","textSize":20,"weight":800,"isLine":1,"lineColor":"#FF0000","isSub":1,"subtextSize":14,"subtextColor":"#FF0000","subbackground":"#FFCCCC","isMore":1,"moretextColor":"#FF0000","background":"#F2f2f2","textColor":"#FF0000","type":1},"params":{"title":"\u5546\u54c1\u63a8\u8350","subtitle":"\u597d\u7269\u5206\u4eab","moretitle":"\u66f4\u591a","show_icon":"yes","icon":"","linkUrl":"","sublinkUrl":""}},{"name":"\u5546\u54c1\u7ec4","type":"product","group":"shop","icon":"icon-shangping","params":{"source":"auto","auto":{"category":64,"productSort":"all","showNum":"4","productName":1,"productPrice":1},"column":2,"display":"list","productName":1,"productPrice":1},"style":{"paddingTop":"","paddingBottom":0,"paddingLeft":10,"topRadio":0,"bottomRadio":8,"bgcolor":"#F2f2f2","background":"#F6F6F6","display":"list","column":2,"show":{"productName":1,"productPrice":1,"linePrice":1,"sellingPoint":0,"productSales":0,"paddingTop":0,"paddingBottom":0,"paddingLeft":10}},"defaultData":[{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100"}],"data":[{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100","is_default":true},{"product_name":"\u6b64\u5904\u663e\u793a\u5546\u54c1\u540d\u79f0","image":"https:\/\/multi3.jjjshop.net\/image\/diy\/product\/01.png","product_price":"99.00","line_price":"139.00","selling_point":"\u6b64\u6b3e\u5546\u54c1\u7f8e\u89c2\u5927\u65b9 \u4e0d\u5bb9\u9519\u8fc7","product_sales":"100","is_default":true}]}]}';
            $data = [
                'page_type' => 30,
                'page_name' => '个人中心',
                'page_data' => json_decode($page_data, 1),
                'is_default' => 1,
                'app_id' => $app_id
            ];
            return (new static())->save($data);
        }
    }
}
