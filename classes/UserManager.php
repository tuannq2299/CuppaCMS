<?php
	class UserManager{
		private static $instance;
		public function UserManager(){ }
		public static function getInstance() {
			if (self::$instance == NULL) { self::$instance = new UserManager(); } 
			return self::$instance;
		}
        /* $access_type: admin_login, site_login 
		      $user = username, email
        */
        public function setSession($access_type = "admin_login", $user = "", $password = ""){
            if(trim($user)) $_POST["user"] = trim($user); if(trim($password)) $_POST["password"] = trim($password);
            $_POST["password_no_enconde"] = @$_POST["password"];
			$configuration = new Configuration();
			$security = Security::getInstance();
			$db = DataBase::getInstance();
            $cuppa = Cuppa::getInstance();
            //++ Encode types
                if($configuration->global_encode == "md5" && @$_POST["password"]) @$_POST["password"] = md5($db->scape(@$_POST["password"]));
                else if($configuration->global_encode == "sha1" && @$_POST["password"]) @$_POST["password"] = sha1($db->scape(@$_POST["password"]));
                else if($configuration->global_encode == "sha1Salt" && @$_POST["password"]) @$_POST["password"] = $cuppa->utils->sha1Salt($db->scape(@$_POST["password"]), $cuppa->configuration->global_encode_salt);
            //--            
			$sql = "SELECT * FROM ".$configuration->table_prefix."users AS u WHERE enabled = '1' AND username = '".$db->scape(@$_POST["user"])."' AND password = '".@$_POST["password"]."'";
			$result = $db->sql($sql);
			if($result == 1 || !$result){
			    @$_POST["email"] = @$_POST["user"];
			    return $this->setSessionByEmail($access_type, @$_POST["email"], @$_POST["password_no_enconde"]);
			} 
			$access = $this->getAccessTypes(@$result[0]["user_group_id"]);
			if(@$access[$access_type]){
				@session_start();
                //++ Register vars in $_SESSION['cuSession']->user 
                    foreach ($result[0] as $key => $value){ if($key != "password") $this->setVar($key, $value); }
                    foreach (@$access as $key => $value){ if($key != "id" || $key != "enabled")  $this->setVar($key, @$value); };
                //--
                return true;
			}
            return false;
		}
        public function login($access_type = "admin_login", $user = "", $password = ""){
            return $this->setSession($access_type, $user, $password);  
        }
        public function setSessionById($access_type = "admin_login", $id){
			$configuration = new Configuration();
			$security = Security::getInstance();
			$db = DataBase::getInstance();
            $cuppa = Cuppa::getInstance();
			$sql = "SELECT * FROM ".$configuration->table_prefix."users AS u WHERE enabled = '1' AND id = '".$id."'";
			$result = $db->sql($sql);
			if($result == 1 || !$result) return;
			$access = $this->getAccessTypes(@$result[0]["user_group_id"]);
			if(@$access[$access_type]){
				@session_start();
                //++ Register vars in $_SESSION['cuSession']->user 
                    foreach ($result[0] as $key => $value){ if($key != "password") $this->setVar($key, $value); }
                    $this->setVar("user_group_name", @$access->name);
                    foreach (@$access as $key => $value){ if($key != "id" && $key != "enabled" && $key != "name")  $this->setVar($key, @$value); };
                //--
                return true;
			}
            return false;
		}
        public function setSessionByEmail($access_type = "admin_login", $email = "", $password = ""){
            if(trim($email)) $_POST["email"] = trim($email); if(trim($password)) $_POST["password"] = trim($password);
			$configuration = new Configuration();
			$security = Security::getInstance();
			$db = DataBase::getInstance();
            $cuppa = Cuppa::getInstance();
            //++ Encode types
                if($configuration->global_encode == "md5" && @$_POST["password"]) $_POST["password"] = md5($db->scape($_POST["password"]));
                else if($configuration->global_encode == "sha1" && @$_POST["password"]) $_POST["password"] = sha1($db->scape($_POST["password"]));
                else if($configuration->global_encode == "sha1Salt" && @$_POST["password"]) $_POST["password"] = $cuppa->utils->sha1Salt($db->scape($_POST["password"]), $cuppa->configuration->global_encode_salt);
            //--  
			$sql = "SELECT * FROM ".$configuration->table_prefix."users AS u WHERE enabled = '1' AND email = '".$db->scape(@$_POST["email"])."' AND password = '".@$_POST["password"]."'";
			$result = $db->sql($sql);
			if($result == 1 || !$result) return;
            $access = $this->getAccessTypes(@$result[0]["user_group_id"]);
            
			if(@$access[$access_type]){
				@session_start();
                //++ Register vars in $_SESSION['cuSession']->user 
                    foreach ($result[0] as $key => $value){ if($key != "password") $this->setVar($key, $value); }
                    $this->setVar("user_group_name", @$access->name);
                    foreach (@$access as $key => $value){ if($key != "id" && $key != "enabled" && $key != "name")  $this->setVar($key, @$value); };
                //--
                return true;
			}
            return false;
		}
        public function setVar($name, $value){
            @session_start();
            if(!@$_SESSION['cuSession']->user) @$_SESSION['cuSession']->user = new stdClass();
            $_SESSION['cuSession']->user->{$name} = $value;
        }
        public function setValue($name, $value){ $this->setVar($name, $value); }
        public function getVar($name){
            @session_start();
            return @$_SESSION['cuSession']->user->{$name};
        }
        public function getValue($name){ return $this->getVar($name); }
        public function value($name, $value = null){
            if($value === null) return $this->getVar($name);
            else $this->setVar($name, $value);
        }
		public function destroy($redirect = "", $create_unregistered_user = false, $user_group_name = "Unregistered"){
            @session_start();
            @$_SESSION['cuSession']->user = new stdClass();
            if($create_unregistered_user) $this->setUnregisteredUser($user_group_name);
            if($redirect) header ("Location: " . $redirect);
		}
        public function setUnregisteredUser($user_group_name = "Unregistered"){
            @session_start();
            if( !$this->getVar("id") && !$this->getVar("user_group_name")  ){
                $configuration = new Configuration(); 
                $db = DataBase::getInstance();
                $user_group = $db->getRow($configuration->table_prefix."user_groups", "name = '".$user_group_name."'", true);
                 if($user_group){
                    $this->setVar("id", 0);
                    $this->setVar("user_group_id", $user_group->id);
                    $this->setVar("user_group_name", @$user_group_name);
                    foreach (@$user_group as $key => $value){ if($key != "id" && $key != "enabled" && $key != "name")  $this->setVar($key, @$value); };
                }            
                if(!@$_SESSION["cuSession"]->language) @$_SESSION["cuSession"]->language = $configuration->language_default;
            }
        }
		public function getAccessTypes($user_group_id){
			if(!$user_group_id) return;
			$configuration = new Configuration();
			$db = DataBase::getInstance();
			$result = $db->getRow($configuration->table_prefix."user_groups","id = $user_group_id AND enabled = 1");
			$array = array();
            foreach ($result as $key => $value){ 
                if($key != "id" && $key != "enabled" && $key != "name"){ 
                    $array[$key] = $value;
                }else if($key == "name"){
                    $array["user_group_name"] = $value;
                }
            };
			return $array;
		}
        public function valid($type = "admin_login"){
            @session_start();
            if(!$this->value($type)){ echo "<script> window.location.href = ''; </script>"; exit(); }
        }
	}
?>