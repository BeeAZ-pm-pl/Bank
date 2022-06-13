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
       $player->sendMessage("§f§lCách Sử Dụng §b/checkbank (name)");
       return true;
     }
    $data = $this->getServer()->getPlayerByPrefix($args[0]);
    $player->sendMessage("§f§lSố Tiền Trong Bank Của §b".strtolower($data->getName())." §fLà §e".$this->myWallet($data));
       return true;
      
     }
    if(strtolower($cmd->getName() === "setbank")){
    if(!isset($args[0]) || $this->getServer()->getPlayerByPrefix($args[0]) == null){
       $player->sendMessage("§f§lCách Sử Dụng §b/setbank (name) (money)");
       return true;
    }
    if(!isset($args[1]) || !is_numeric($args[1])){
       $player->sendMessage("§f§lCách Sử Dụng §b/setbank (name) (money)");
       return true;
    }
    $data = $this->getServer()->getPlayerByPrefix($args[0]);
    $name = strtolower($data->getName());
    $money = abs($args[1]);
    $this->db->query("UPDATE bank SET money = $money WHERE name = '$name'");
    $event = new BankChangedEvent($this, $data);
    $event->call();
    $player->sendMessage("§f§lBạn Đã Chỉnh Số Tiền Trong Bank Của §b".$name." §fThành §e".$money);
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
   $form->setTitle("§b§lＢＡＮＫ");
   $form->setContent("§e§l↣ §fSố Tiền Trong Bank : §b{$money}\n§e§l↣ §fSố Tiền Lãi Mỗi Phút : §b{$rate}");
   $form->addButton("§f§l• §0Gửi Tiền §f•");
   $form->addButton("§f§l• §0Rút Tiền §f•");
   $form->addButton("§f§l• §0Chuyển Tiền §f•");
   $form->sendToPlayer($player);
  }
  
  public function sendmoney($player){
   $form = new CustomForm(function(Player $player , $data){
   if($data === null){
    $this->bank($player);
    return true;
   }
   if(!isset($data[1]) || !is_numeric($data[1])){
    $player->sendMessage("§f§lBạn không thể nhập dữ liệu khác ngoài số");
    return true;
   }
   if($data[1] < 1000){
    $player->sendMessage("§f§lBạn không thể gửi số tiền dưới 1000 vào bank");
    return true;
   }
   $money = $data[1];
   $name = strtolower($player->getName());
   if($this->eco->myMoney($player) >= $money){
   $this->eco->reduceMoney($player, $money);
   $this->db->query("UPDATE bank SET money = money + $money WHERE name = '$name'");
   $event = new BankChangedEvent($this, $player);
   $event->call();
   $player->sendMessage("§f§lBạn Đã Gửi Thành Công §b".$money." §fVào Bank");
  }else{
   $player->sendMessage("§f§lBạn Không Đủ §b".$money." §fĐể Gửi Vào Bank");
    }
   });
   $money = $this->myWallet($player);
   $rate = round($this->myWallet($player) * self::INTEREST, 6);
   $form->setTitle("§b§lＳＥＮＤ ＭＯＮＥＹ");
   $form->addLabel("§e§l↣ §fSố Tiền Trong Bank : §b{$money}\n§e§l↣ §fSố Tiền Lãi Mỗi Phút : §b{$rate}");
   $form->addInput("§e§l↣ §fNhập Money Muốn Gửi :", 1000);
   $form->sendToPlayer($player);
  }
  
  public function reducemoney($player){
   $form = new CustomForm(function(Player $player , $data){
   if($data === null){
    $this->bank($player);
    return true;
   }
   if(!isset($data[1]) || !is_numeric($data[1])){
    $player->sendMessage("§f§lBạn không thể nhập dữ liệu khác ngoài số");
    return true;
   }
   if($data[1] < 1000){
    $player->sendMessage("§f§lBạn không thể rút số tiền dưới 1000 trong bank");
    return true;
   }
   $money = $data[1];
   $name = strtolower($player->getName());
   if($this->myWallet($player) >= $money){
   $this->eco->addMoney($player, $money);
   $this->db->query("UPDATE bank SET money = money - $money WHERE name = '$name'");
   $event = new BankChangedEvent($this, $player);
   $event->call();
   $player->sendMessage("§f§lBạn Đã Rút Thành Công §b".$money." §fKhỏi Bank");
  }else{
   $player->sendMessage("§f§lBạn Không Đủ §b".$money." §fĐể Rút Khỏi Bank");
    }
   });
   $money = $this->myWallet($player);
   $rate = round($this->myWallet($player) * self::INTEREST, 6);
   $form->setTitle("§b§lＲＥＤＵＣＥ ＭＯＮＥＹ");
   $form->addLabel("§e§l↣ §fSố Tiền Trong Bank : §b{$money}\n§e§l↣ §fSố Tiền Lãi Mỗi Phút : §b{$rate}");
   $form->addInput("§e§l↣ §fNhập Money Muốn Rút :", 1000);
   $form->sendToPlayer($player);
  }
  
  public function transfermoney($player){
   $form = new CustomForm(function(Player $player , $data){
   if($data === null){
    $this->bank($player);
    return true;
   }
   if(!isset($data[1]) || !is_numeric($data[1])){
    $player->sendMessage("§f§lBạn không thể nhập dữ liệu khác ngoài số");
    return true;
   }
   if($data[1] < 1000){
    $player->sendMessage("§f§lBạn không thể chuyển số tiền dưới 1000 cho người khác");
    return true;
   }
   if(!isset($data[2]) || $this->getServer()->getPlayerByPrefix($data[2]) == null){
    $player->sendMessage("§f§lNgười Chơi Bạn Nhập Không Trực Tuyến");
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
   $player->sendMessage("§f§lBạn Chuyển Thành Công §b".$money." §fĐến Tài Khoản Của §e".$namedata." §fVới Phí Là §b".$charge." §fXu");
   $playerdata->sendMessage("§f§lBạn Đã Nhận Được §b".$money." §fTừ Tài Khoản §b".$namedata);
  }else{
   $player->sendMessage("§f§lBạn Không Đủ §b".$charges." §fTrong Bank");
    }
   });
   $money = $this->myWallet($player);
   $rate = round($this->myWallet($player) * self::INTEREST, 6);
   $charge = 1000;
   $form->setTitle("§l§bＴＲＡＮＳＦＥＲ ＭＯＮＥＹ");
   $form->addLabel("§e§l↣ §fSố Tiền Trong Bank : §b{$money}\n§e§l↣ §fSố Tiền Lãi Mỗi Phút : §b{$rate}\n§e§l↣ §fPhí Chuyển Tiền : §b{$charge}");
   $form->addInput("§e§l↣ §fNhập Money Muốn Chuyển :", 1000);
   $form->addInput("§e§l↣ §fNhập Người Muốn Chuyển :", "abc");
   $form->sendToPlayer($player);
  }
}
