<?php

namespace BeeAZ\Bank;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use BeeAZ\Bank\event\BankChangedEvent;
use BeeAZ\Bank\task\BankTask;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use SQLite3;

class Main extends PluginBase implements Listener{
  
  public const INTEREST = 0.0000100;
  
  public $login = [];
  
  public const SERVER = "play.thevertie.xyz";
  
  public const PORT = "19132";
  
  public function onEnable(): void{
   $this->getServer()->getPluginManager()->registerEvents($this, $this);
   $this->eco = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
   $this->db = new \SQLite3($this->getDataFolder()."bank.db");
   $this->db->exec("CREATE TABLE IF NOT EXISTS bank (name TEXT PRIMARY KEY NOT NULL, money INTEGER default 0 NOT NULL);");
   $this->getScheduler()->scheduleRepeatingTask(new BankTask($this), 20);
  }
  
  public function onDisable():void{
   $this->db->close();
  }
  
  public function myWallet($player){
   $name = strtolower($player->getName());
   $data = $this->db->query("SELECT * FROM bank WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
   return $data["money"];
  }
  
  public function onLogin(PlayerLoginEvent $event){
   $player = $event->getPlayer();
   $name = strtolower($player->getName());
   $this->login[$name] = 0;
   $data = $this->db->query("SELECT * FROM bank WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
   if($data == false){
     $this->db->query("INSERT INTO bank (name) VALUES ('$name');");
     $event = new BankChangedEvent($this, $player);
     $event->call();
    }
   }
   
  public function onQuit(PlayerQuitEvent $event){
   $player = $event->getPlayer();
   $name = strtolower($player->getName());
   unset($this->login[$name]);
  }
  
  public function interest($player){
   $name = strtolower($player->getName());
   $rate = $this->myWallet($player) * self::INTEREST;
   $this->login[$name]++;
   if($this->login[$name] == 60){
     $this->db->query("UPDATE bank SET money = money + $rate WHERE name = '$name'");
     $event = new BankChangedEvent($this, $player);
     $event->call();
     $this->login[$name] = 0;
    }
   }
  
  public function onCommand(CommandSender $player, Command $cmd, string $label, array $args):bool{
    if(strtolower($cmd->getName() === "bank")){
      $this->bank($player);
      return true;
     }
    if(strtolower($cmd->getName() === "checkbank")){
    if(!isset($args[0]) || $this->getServer()->getPlayerByPrefix($args[0]) == null){
       $player->sendMessage("??f??lC??ch S??? D???ng ??b/checkbank (name)");
       return true;
     }
    $data = $this->getServer()->getPlayerByPrefix($args[0]);
    $player->sendMessage("??f??lS??? Ti???n Trong Bank C???a ??b".strtolower($data->getName())." ??fL?? ??e".$this->myWallet($data));
       return true;
      
     }
    if(strtolower($cmd->getName() === "setbank")){
    if(!isset($args[0]) || $this->getServer()->getPlayerByPrefix($args[0]) == null){
       $player->sendMessage("??f??lC??ch S??? D???ng ??b/setbank (name) (money)");
       return true;
    }
    if(!isset($args[1]) || !is_numeric($args[1])){
       $player->sendMessage("??f??lC??ch S??? D???ng ??b/setbank (name) (money)");
       return true;
    }
    $data = $this->getServer()->getPlayerByPrefix($args[0]);
    $name = strtolower($data->getName());
    $money = abs($args[1]);
    $this->db->query("UPDATE bank SET money = $money WHERE name = '$name'");
    $event = new BankChangedEvent($this, $data);
    $event->call();
    $player->sendMessage("??f??lB???n ???? Ch???nh S??? Ti???n Trong Bank C???a ??b".$name." ??fTh??nh ??e".$money);
    }
    return true;
  }
  
  public function bank($player){
   $form = new SimpleForm(function(Player $player , $data){
   if($data === null){
    return true;
   }
   switch($data){
    case 0:
    $this->sendmoney($player);
    break;
    case 1:
    $this->reducemoney($player);
    break;
    case 2:
    $this->transfermoney($player);
    break;
    }
   });
   $money = $this->myWallet($player);
   $rate = round($this->myWallet($player) * self::INTEREST, 6);
   $form->setTitle("??b??l????????????");
   $form->setContent("??e??l??? ??fS??? Ti???n Trong Bank : ??b{$money}\n??e??l??? ??fS??? Ti???n L??i M???i Ph??t : ??b{$rate}");
   $form->addButton("??f??l??? ??0G???i Ti???n ??f???");
   $form->addButton("??f??l??? ??0R??t Ti???n ??f???");
   $form->addButton("??f??l??? ??0Chuy???n Ti???n ??f???");
   $form->sendToPlayer($player);
  }
  
  public function sendmoney($player){
   $form = new CustomForm(function(Player $player , $data){
   if($data === null){
    $this->bank($player);
    return true;
   }
   if(!isset($data[1]) || !is_numeric($data[1])){
    $player->sendMessage("??f??lB???n kh??ng th??? nh???p d??? li???u kh??c ngo??i s???");
    return true;
   }
   if($data[1] < 1000){
    $player->sendMessage("??f??lB???n kh??ng th??? g???i s??? ti???n d?????i 1000 v??o bank");
    return true;
   }
   $money = $data[1];
   $name = strtolower($player->getName());
   if($this->eco->myMoney($player) >= $money){
   $this->eco->reduceMoney($player, $money);
   $this->db->query("UPDATE bank SET money = money + $money WHERE name = '$name'");
   $event = new BankChangedEvent($this, $player);
   $event->call();
   $player->sendMessage("??f??lB???n ???? G???i Th??nh C??ng ??b".$money." ??fV??o Bank");
  }else{
   $player->sendMessage("??f??lB???n Kh??ng ????? ??b".$money." ??f????? G???i V??o Bank");
    }
   });
   $money = $this->myWallet($player);
   $rate = round($this->myWallet($player) * self::INTEREST, 6);
   $form->setTitle("??b??l???????????? ???????????????");
   $form->addLabel("??e??l??? ??fS??? Ti???n Trong Bank : ??b{$money}\n??e??l??? ??fS??? Ti???n L??i M???i Ph??t : ??b{$rate}");
   $form->addInput("??e??l??? ??fNh???p Money Mu???n G???i :", 1000);
   $form->sendToPlayer($player);
  }
  
  public function reducemoney($player){
   $form = new CustomForm(function(Player $player , $data){
   if($data === null){
    $this->bank($player);
    return true;
   }
   if(!isset($data[1]) || !is_numeric($data[1])){
    $player->sendMessage("??f??lB???n kh??ng th??? nh???p d??? li???u kh??c ngo??i s???");
    return true;
   }
   if($data[1] < 1000){
    $player->sendMessage("??f??lB???n kh??ng th??? r??t s??? ti???n d?????i 1000 trong bank");
    return true;
   }
   $money = $data[1];
   $name = strtolower($player->getName());
   if($this->myWallet($player) >= $money){
   $this->eco->addMoney($player, $money);
   $this->db->query("UPDATE bank SET money = money - $money WHERE name = '$name'");
   $event = new BankChangedEvent($this, $player);
   $event->call();
   $player->sendMessage("??f??lB???n ???? R??t Th??nh C??ng ??b".$money." ??fKh???i Bank");
  }else{
   $player->sendMessage("??f??lB???n Kh??ng ????? ??b".$money." ??f????? R??t Kh???i Bank");
    }
   });
   $money = $this->myWallet($player);
   $rate = round($this->myWallet($player) * self::INTEREST, 6);
   $form->setTitle("??b??l?????????????????? ???????????????");
   $form->addLabel("??e??l??? ??fS??? Ti???n Trong Bank : ??b{$money}\n??e??l??? ??fS??? Ti???n L??i M???i Ph??t : ??b{$rate}");
   $form->addInput("??e??l??? ??fNh???p Money Mu???n R??t :", 1000);
   $form->sendToPlayer($player);
  }
  
  public function transfermoney($player){
   $form = new CustomForm(function(Player $player , $data){
   if($data === null){
    $this->bank($player);
    return true;
   }
   if(!isset($data[1]) || !is_numeric($data[1])){
    $player->sendMessage("??f??lB???n kh??ng th??? nh???p d??? li???u kh??c ngo??i s???");
    return true;
   }
   if($data[1] < 1000){
    $player->sendMessage("??f??lB???n kh??ng th??? chuy???n s??? ti???n d?????i 1000 cho ng?????i kh??c");
    return true;
   }
   if(!isset($data[2]) || $this->getServer()->getPlayerByPrefix($data[2]) == null){
    $player->sendMessage("??f??lNg?????i Ch??i B???n Nh???p Kh??ng Tr???c Tuy???n");
    return true;
   }
   $money = $data[1];
   $charge = 1000;
   $charges = $money + $charge;
   $name = strtolower($player->getName());
   $playerdata = $this->getServer()->getPlayerByPrefix($data[2]);
   $namedata = strtolower($playerdata->getName());
   if($this->myWallet($player) >= $charges){
   $this->db->query("UPDATE bank SET money = money + $money WHERE name = '$namedata'");
   $this->db->query("UPDATE bank SET money = money - $charges WHERE name = '$name'");
   $event = new BankChangedEvent($this, $player);
   $event->call();
   $event2 = new BankChangedEvent($this, $playerdata);
   $event2->call();
   $player->sendMessage("??f??lB???n Chuy???n Th??nh C??ng ??b".$money." ??f?????n T??i Kho???n C???a ??e".$namedata." ??fV???i Ph?? L?? ??b".$charge." ??fXu");
   $playerdata->sendMessage("??f??lB???n ???? Nh???n ???????c ??b".$money." ??fT??? T??i Kho???n ??b".$name);
  }else{
   $player->sendMessage("??f??lB???n Kh??ng ????? ??b".$charges." ??fTrong Bank");
    }
   });
   $money = $this->myWallet($player);
   $rate = round($this->myWallet($player) * self::INTEREST, 6);
   $charge = 1000;
   $form->setTitle("??l??b???????????????????????? ???????????????");
   $form->addLabel("??e??l??? ??fS??? Ti???n Trong Bank : ??b{$money}\n??e??l??? ??fS??? Ti???n L??i M???i Ph??t : ??b{$rate}\n??e??l??? ??fPh?? Chuy???n Ti???n : ??b{$charge}");
   $form->addInput("??e??l??? ??fNh???p Money Mu???n Chuy???n :", 1000);
   $form->addInput("??e??l??? ??fNh???p Ng?????i Mu???n Chuy???n :", "abc");
   $form->sendToPlayer($player);
  }
}
