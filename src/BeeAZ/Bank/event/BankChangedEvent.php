<?php

namespace BeeAZ\bank\event;

use BeeAZ\bank\Main;
use BeeAZ\bank\event\BankEvent;

class BankChangedEvent extends BankEvent{
  
  public $plugin;
  
  public $player;
  
  public function __construct(Main $plugin, $player){
    $this->plugin = $plugin;
    $this->player = $player;
  }
  
  public function getPlayer(){
    return $this->player;
  }
  
  public function getWallet(){
    return $this->plugin->myWallet($this->player);
  }
}