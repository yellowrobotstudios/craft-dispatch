<?php

namespace yellowrobot\craftdispatch\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%craftdispatch_templates}}')) {
            $this->createTable('{{%craftdispatch_templates}}', [
                'id' => $this->primaryKey(),
                'handle' => $this->string(255)->notNull(),
                'subject' => $this->string(255)->notNull(),
                'htmlBody' => $this->text()->notNull(),
                'textBody' => $this->text()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(
                null,
                '{{%craftdispatch_templates}}',
                'id',
                '{{%elements}}',
                'id',
                'CASCADE',
                null,
            );

            $this->createIndex(null, '{{%craftdispatch_templates}}', ['handle'], true);
        }

        if (!$this->db->tableExists('{{%craftdispatch_logs}}')) {
            $this->createTable('{{%craftdispatch_logs}}', [
                'id' => $this->primaryKey(),
                'templateHandle' => $this->string(255)->notNull(),
                'recipient' => $this->text()->notNull(),
                'subject' => $this->string(255)->notNull(),
                'status' => $this->string(20)->notNull()->defaultValue('queued'),
                'errorMessage' => $this->text()->null(),
                'elementId' => $this->integer()->null(),
                'elementType' => $this->string(255)->null(),
                'dateSent' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%craftdispatch_logs}}', ['templateHandle']);
            $this->createIndex(null, '{{%craftdispatch_logs}}', ['dateSent']);
            $this->createIndex(null, '{{%craftdispatch_logs}}', ['status']);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%craftdispatch_logs}}');
        $this->dropTableIfExists('{{%craftdispatch_templates}}');

        return true;
    }
}
