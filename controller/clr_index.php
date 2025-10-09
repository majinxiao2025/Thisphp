<?php
/**
 * Copyright © 2017-2021 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2021/11/21
 * Time: 09:36
 */
class clr_index extends controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        $this->view();
    }

}