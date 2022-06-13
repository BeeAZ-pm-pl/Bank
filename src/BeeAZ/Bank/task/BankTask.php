<?php

namespace BeeAZ\Bank\task;

use pocketmine\scheduler\Task;

class BankTask extends Task{
  
  public function __construct($plugin){
   $this->plugin = $plugin;
  }
  
  public function onRun():void{
   foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
   $this->plugin->interest($player);
}
}
}