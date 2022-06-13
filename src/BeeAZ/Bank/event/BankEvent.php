<?php

namespace BeeAZ\bank\event;

use BeeAZ\Bank\Main;
use pocketmine\event\plugin\PluginEvent;

class BankEvent extends PluginEvent{
  
  public $plugin;
  
  public function __construct(Main $plugin){
    $this->plugin = $plugin;
  }
}