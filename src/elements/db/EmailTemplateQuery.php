<?php

namespace yellowrobot\craftdispatch\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class EmailTemplateQuery extends ElementQuery
{
    public ?string $handle = null;
    public ?string $subject = null;

    public function handle(?string $value): static
    {
        $this->handle = $value;
        return $this;
    }

    public function subject(?string $value): static
    {
        $this->subject = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('craftdispatch_templates');

        $this->query->select([
            'craftdispatch_templates.handle',
            'craftdispatch_templates.subject',
            'craftdispatch_templates.htmlBody',
            'craftdispatch_templates.textBody',
        ]);

        if ($this->handle !== null) {
            $this->subQuery->andWhere(Db::parseParam('craftdispatch_templates.handle', $this->handle));
        }

        if ($this->subject !== null) {
            $this->subQuery->andWhere(Db::parseParam('craftdispatch_templates.subject', $this->subject));
        }

        return parent::beforePrepare();
    }
}
