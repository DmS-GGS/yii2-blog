<?php

use yii\db\Schema;
use yii\db\Migration;

class m000003_000001_blog_post extends Migration
{

    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $this->createTable(
            '{{%blog_post}}',
            [
                'id' => $this->primaryKey(11),
                'title' => $this->string(191)->notNull(),
                'content' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->null()->defaultValue(null),
//                'content' => $this->text()->null()->defaultValue(null),
                'brief' => $this->text()->null()->defaultValue(null),
                'created_at' => $this->integer(11)->defaultValue(0),
                'published_at' => $this->integer(11)->defaultValue(0),
                'banner' => $this->string(191)->null()->defaultValue(null),
                'user_id' => $this->integer(11)->null()->defaultValue(null),
                'slug' => $this->string(191)->unique(),
                'tags' => $this->string(191)->null()->defaultValue(null),
                'click' => $this->integer(11)->defaultValue(0),
                'show_comments' => $this->tinyInteger(1)->null()->defaultValue(null),
                'status' => $this->integer(11)->notNull()->defaultValue(1),
                'updated_at' => $this->integer(11)->defaultValue(0),
                'category_id' => $this->integer(11)->null()->defaultValue(0),
                'is_slide' => $this->boolean(),
                'type_id' => $this->integer(11)->notNull(),
            ], $tableOptions
        );

        $this->createIndex('category_id', '{{%blog_post}}', ['category_id'], false);
        $this->createIndex('type_id', '{{%blog_post}}', ['type_id'], false);
        $this->createIndex('status', '{{%blog_post}}', ['status'], false);
        $this->createIndex('created_at', '{{%blog_post}}', ['created_at'], false);
        $this->createIndex('updated_at', '{{%blog_post}}', ['updated_at'], false);
        $this->createIndex('published_at', '{{%blog_post}}', ['published_at'], false);

        $this->addForeignKey('fk_blog_post_type_id',
            '{{%blog_post}}', 'type_id',
            '{{%blog_post_type}}', 'id',
            'CASCADE', 'CASCADE'
        );

    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_blog_post_type_id', '{{%blog_post}}');
        $this->dropIndex('slug', '{{%blog_post}}');
        $this->dropIndex('category_id', '{{%blog_post}}');
        $this->dropIndex('status', '{{%blog_post}}');
        $this->dropIndex('created_at', '{{%blog_post}}');
        $this->dropIndex('updated_at', '{{%blog_post}}');
        $this->dropIndex('published_at', '{{%blog_post}}');
        $this->dropTable('{{%blog_post}}');
    }
}
