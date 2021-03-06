<?php
/**
 * Project: yii2-blog for internal using
 * Author: diazoxide
 * Copyright (c) 2018.
 */

namespace diazoxide\blog\models;

use diazoxide\blog;
use diazoxide\blog\Module;
use diazoxide\blog\traits\IActiveStatus;
use diazoxide\blog\traits\ModuleTrait;
use diazoxide\blog\traits\StatusTrait;
use voskobovich\behaviors\ManyToManyBehavior;
use Yii;
use yii\base\InvalidConfigException;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\SluggableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\UrlRule;
use yiidreamteam\upload\ImageUploadBehavior;

/**
 * This is the model class for table "blog_post".
 *
 * @property integer       $id
 * @property integer       $type_id
 * @property integer       $category_id
 * @property integer[]     $category_ids
 * @property string        $title
 * @property string        $url
 * @property boolean       $show_comments
 * @property boolean       $is_slide
 * @property string        $content
 * @property string        $brief
 * @property string        $tags
 * @property string        $slug
 * @property string        $banner
 * @property integer       $click
 * @property integer       $user_id
 * @property integer       $status
 * @property integer       $created_at
 * @property integer       $updated_at
 * @property integer       $published_at
 * @property BlogComment[] $blogComments
 * @property BlogCategory  $category
 * @property BlogPostBook  $books
 * @property Module        module
 * @property BlogPostType  $type
 * @method getThumbFileUrl($attribute, $thumbType)
 */
class BlogPost extends ActiveRecord
{
    use StatusTrait, ModuleTrait;

    private $_oldTags;

    private $_status;

    public $created;

    public $updated;

    public $published;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%blog_post}}';
    }

    /**
     * created_at, updated_at to now()
     * crate_user_id, update_user_id to current login user id
     *
     * @throws InvalidConfigException
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class
            ],
            [
                'class'      => blog\behaviors\DataOptionsBehavior\Behavior::class,
                'data_model' => BlogPostData::class
            ],
            [
                'class'         => SluggableBehavior::class,
                'attribute'     => 'title',
                'slugAttribute' => 'slug',
                'immutable'     => true,
                'ensureUnique'  => true,
            ],
            [
                'class'      => AttributeBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'user_id'
                ],
                'value'      => function ($event) {
                    return Yii::$app->user->getId();
                },
            ],
            [
                'class'     => ManyToManyBehavior::class,
                'relations' => [
                    'category_ids' => 'categories',
                ],
            ],

            /*
             * Image upload behaviour with thumbnails generator
             * */
            [
                'class'     => ImageUploadBehavior::class,
                'attribute' => 'banner',
                'thumbs'    => $this->module->getThumbnailsSizes(),
                'filePath'  => $this->module->imgFilePath
                    . '/post/[[attribute_type_id]]/[[pk]].[[extension]]',
                'fileUrl'   => $this->module->getImgFullPathUrl()
                    . '/post/[[attribute_type_id]]/[[pk]].[[extension]]',
                'thumbPath' => $this->module->imgFilePath
                    . '/post/[[attribute_type_id]]/[[profile]]_[[pk]].[[extension]]',
                'thumbUrl'  => $this->module->getImgFullPathUrl()
                    . '/post/[[attribute_type_id]]/[[profile]]_[[pk]].[[extension]]',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            /*
             * Title always required for every type of post
             * */
            [['title', 'type_id'], 'required'],

            /*
             * Additional property for many_to_many behavior
             * For multiple categories
             * */
            [['category_ids'], 'each', 'rule' => ['integer']],

            /*
             * Require category_id when post type has_category property is true
             * Also disable client validation for this property
             * */
            [
                'category_id',
                'required',
                'when'                   => function ($model) {
                    return $model->type->has_category;
                },
                'enableClientValidation' => false
            ],

            /*
             * Check if category type is same of post type
             * */
            ['category_id', 'categoryValidation'],


            [
                ['category_id', 'click', 'type_id', 'user_id', 'status'],
                'integer'
            ],
            [
                ['created_at', 'updated_at', 'published_at'],
                'integer',
                'min' => 0
            ],

            [['brief', 'content'], 'string'],
            [['is_slide', 'show_comments'], 'boolean'],
            [['show_comments'], 'default', 'value' => true],
            [
                ['banner'],
                'file',
                'extensions' => 'jpg, png, webp, jpeg',
                'mimeTypes'  => 'image/jpeg, image/png, image/webp',
            ],
            [['title', 'tags', 'slug'], 'string', 'max' => 191],
            ['click', 'default', 'value' => 0],

            [
                ['created', 'updated', 'published'],
                'date',
                'format' => Yii::$app->formatter->datetimeFormat
            ],

            [['slug'], 'unique'],
            /*
             * Check if type id is exists in BlogPostType table
             * */
            [
                'type_id',
                'exist',
                'targetClass'     => BlogPostType::class,
                'targetAttribute' => 'id'
            ]

        ];
    }

    public function categoryValidation($attribute, $params)
    {
        if ($this->category->type_id != $this->type_id
            && $this->category->type_id != null
        ) {
            $this->addError(
                $attribute, Module::t(
                '', 'Post type must be same type as the category type.'
            )
            );

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'            => Module::t('', 'ID'),
            'category_id'   => Module::t('', 'Category'),
            'category_ids'  => Module::t('', 'Categories'),
            'title'         => Module::t('', 'Title'),
            'brief'         => Module::t('', 'Brief'),
            'content'       => Module::t('', 'Content'),
            'tags'          => Module::t('', 'Tags'),
            'slug'          => Module::t('', 'Slug'),
            'banner'        => Module::t('', 'Banner'),
            'show_comments' => Module::t('', 'Show Comments'),
            'click'         => Module::t('', 'Click'),
            'user_id'       => Module::t('', 'Author'),
            'status'        => Module::t('', 'Status'),
            'created_at'    => Module::t('', 'Created At'),
            'updated_at'    => Module::t('', 'Updated At'),
            'published_at'  => Module::t('', 'Published At'),
            'is_slide'      => Module::t('', 'Show In Slider'),
            'commentsCount' => Module::t('', 'Comments Count'),
            'created'       => Module::t('', 'Created'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBlogComments()
    {
        return $this->hasMany(BlogComment::class, ['post_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBooks()
    {
        return $this->hasMany(BlogPostBook::class, ['post_id' => 'id']);
    }

    /**
     * @return int|string
     */
    public function getCommentsCount()
    {
        return $this->hasMany(BlogComment::class, ['post_id' => 'id'])->count(
            'post_id'
        );
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(BlogCategory::class, ['id' => 'category_id']);
    }


    /**
     * @return \yii\db\ActiveQuery
     * @throws InvalidConfigException
     */
    public function getCategories()
    {
        return $this->hasMany(BlogCategory::class, ['id' => 'category_id'])
            ->viaTable('{{%blog_category_map}}', ['post_id' => 'id']);
    }


    public function getType()
    {
        return $this->hasOne(BlogPostType::class, ['id' => 'type_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        if (\dektrium\user\models\User::class) {
            return $this->hasOne(
                \dektrium\user\models\User::class, ['id' => 'user_id']
            );
        }

        return null;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getComments()
    {
        return $this->hasMany(BlogComment::class, ['post_id' => 'id']);
    }

    /**
     * After save.
     *
     * @param $insert
     * @param $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        // add your code here
        BlogTag::updateFrequency($this->_oldTags, $this->tags);
    }

    /**
     * After save.
     *
     */
    public function afterDelete()
    {
        parent::afterDelete();
        BlogTag::updateFrequencyOnDelete($this->_oldTags);
    }

    /**
     * This is invoked when a record is populated with data from a find() call.
     */
    public function afterFind()
    {
        parent::afterFind();
        $this->_oldTags = $this->tags;
    }

    /**
     * Normalizes the user-entered tags.
     */
    public static function getArrayCategory()
    {
        return ArrayHelper::map(BlogCategory::find()->all(), 'id', 'title');
    }

    /**
     * Normalizes the user-entered tags.
     */
    public function normalizeTags($attribute, $params)
    {
        $this->tags = BlogTag::array2string(
            array_unique(
                array_map(
                    'trim',
                    BlogTag::string2array($this->tags)
                )
            )
        );
    }


    /**
     * Building post url,
     * Using post type url pattern for building custom url
     * For each post type you can create different url routes
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function getUrl()
    {
        /*
         * If backend detected
         * Than return post edit url
         * */
        if ($this->getModule()->getIsBackend()) {
            return Yii::$app->getUrlManager()->createUrl(
                ['blog/blog-post/update', 'id' => $this->id]
            );
        }

        /*
         * Datetime parameters
         * Additional parameters for beauty urls
         * */
        $year  = date('Y', $this->published_at);
        $month = date('m', $this->published_at);
        $day   = date('d', $this->published_at);

        /*
         * If post type has custom url pattern
         * Then method must generate custom rule and rule must generate final url
         * After generation final url "strtok" function deletes last unnecessary get parameters from query string
         * and return url string
         * */
        if ($this->type->url_pattern) {

            $rule = new UrlRule(
                [
                    'pattern' => $this->type->url_pattern,
                    'route'   => 'blog/default/view'
                ]
            );

            $url = $rule->createUrl(
                Yii::$app->getUrlManager(),
                'blog/default/view',
                [
                    'type'  => $this->type->name,
                    'year'  => $year,
                    'month' => $month,
                    'day'   => $day,
                    'slug'  => $this->slug,
                    'id'    => $this->id
                ]
            );

            $baseUrl = Yii::$app->urlManager->showScriptName
            || ! Yii::$app->urlManager->enablePrettyUrl
                ? Yii::$app->urlManager->getScriptUrl()
                : Yii::$app->urlManager->getBaseUrl();

            return $baseUrl . '/' . strtok($url, '?');
        }

        /*
         * If post type don't have url pattern
         * Than return default url
         * */

        return Yii::$app->getUrlManager()->createUrl(
            [
                'blog/default/view',
                'type'  => $this->type->name,
                'year'  => $year,
                'month' => $month,
                'day'   => $day,
                'slug'  => $this->slug
            ]
        );
    }

    /**
     * Getting absolute url of post
     * Including the host info and scheme
     *
     * @param null $scheme
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function getAbsoluteUrl($scheme = null)
    {
        if ($this->getModule()->getIsBackend()) {
            return Yii::$app->getUrlManager()->createAbsoluteUrl(
                ['blog/blog-post/update', 'id' => $this->id]
            );
        }

        $url = $this->getUrl();
        if (strpos($url, '://') === false) {
            $hostInfo = Yii::$app->urlManager->getHostInfo();
            if (strncmp($url, '//', 2) === 0) {
                $url = substr($hostInfo, 0, strpos($hostInfo, '://')) . ':'
                    . $url;
            } else {
                $url = $hostInfo . $url;
            }
        }

        return Url::ensureScheme($url, $scheme);
    }


    /**
     * @return array
     */
    public function getTagLinks()
    {
        $links = [];
        foreach (BlogTag::string2array($this->tags) as $tag) {
            $links[] = Html::a(
                $tag,
                Yii::$app->getUrlManager()->createUrl(
                    ['blog/default/index', 'tag' => $tag]
                )
            );
        }

        return $links;
    }

    /**
     * comment need approval
     *
     * @param BlogComment $comment
     *
     * @return bool
     */
    public function addComment($comment)
    {
        $comment->status  = IActiveStatus::STATUS_INACTIVE;
        $comment->post_id = $this->id;

        return $comment->save();
    }

    /**
     * @return string
     */
    public function getCreatedRelativeTime()
    {
        return Yii::$app->formatter->format($this->created_at, 'relativeTime');
    }

    /**
     * @return string
     */
    public function getUpdatedRelativeTime()
    {
        return Yii::$app->formatter->format($this->updated_at, 'relativeTime');
    }

    /**
     * @return array|mixed
     */
    public function getBreadcrumbs()
    {
        $result = [];

        $result[] = ['label' => $this->type->title, 'url' => $this->type->url];

        if ($this->type->has_category) {
            $result = $this->category->breadcrumbs;
        }

        return $result;
    }


    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getCreated()
    {
        if ($this->isNewRecord) {
            return Module::convertTime(time(), 'datetime');
        }

        return Module::convertTime($this->created_at, 'datetime');
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getUpdated()
    {
        if ($this->isNewRecord) {
            return Module::convertTime(time(), 'datetime');
        }

        return Module::convertTime($this->updated_at, 'datetime');
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getPublished()
    {
        if ($this->isNewRecord) {
            return Module::convertTime(time(), 'datetime');
        }

        return Module::convertTime($this->published_at, 'datetime');
    }

    /**
     * If global comments enabled and
     * Post comments enabled
     * @return bool
     */
    public function showComments(){
        if($this->module->enableComments && $this->show_comments){
            return true;
        }
        return false;
    }

}
