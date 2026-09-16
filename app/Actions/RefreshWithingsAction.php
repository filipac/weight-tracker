<?php

namespace App\Actions;

class RefreshWithingsAction
{
    public function execute()
    {
        return app(\App\Health\WithingsClient::class)->token();
    }
}
