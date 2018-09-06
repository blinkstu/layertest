<?php
namespace app\replacement\controller;
use cmf\controller\AdminBaseController;
use think\Validate;

class AdminReplacementController extends AdminBaseController {

    public function index()
    {
        return $this->fetch('contractReplacement');
    }

    public function addReplacement()
    {
        return $this->fetch('addReplacement');
    }
}