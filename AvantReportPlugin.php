<?php

class AvantReportPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'admin_head',
        'public_head'
    );

    protected $_filters = array(
    );

    public function hookAdminHead($args)
    {
        $this->head();
    }

    public function hookPublicHead($args)
    {
        $this->head();
    }

    protected function head()
    {
    }
}
