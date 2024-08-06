<?php
class TFesp3d extends IPSModule
{
    
    public function Create()
	{
        parent::Create();
		
		$this->RegisterPropertyInteger("wfcID", 0);
		$this->RegisterPropertyString("subject", "");
		$this->RegisterPropertyString("message", "");
		$this->RegisterPropertyInteger("sound",0);

		if (!IPS_VariableProfileExists('TFE3D.exTemp')) 
		{
            IPS_CreateVariableProfile('TFE3D.exTemp', 2);
			IPS_SetVariableProfileIcon ('TFE3D.exTemp', 'Temperature');
			IPS_SetVariableProfileDigits('TFE3D.exTemp', 2);
			IPS_SetVariableProfileText('TFE3D.exTemp', '', ' °C');
			IPS_SetVariableProfileValues('TFE3D.exTemp', 0, 300, 0.1);
		}

		if (!IPS_VariableProfileExists('TFE3D.bedTemp')) 
		{
            IPS_CreateVariableProfile('TFE3D.bedTemp', 2);
			IPS_SetVariableProfileIcon ('TFE3D.bedTemp', 'Temperature');
			IPS_SetVariableProfileDigits('TFE3D.bedTemp', 2);
			IPS_SetVariableProfileText('TFE3D.bedTemp', '', ' °C');
			IPS_SetVariableProfileValues('TFE3D.bedTemp', 0, 110, 0.1);
		}

		if (!IPS_VariableProfileExists('TFE3D.printState')) 
		{
            IPS_CreateVariableProfile('TFE3D.printState', 1);
            IPS_SetVariableProfileAssociation('TFE3D.printState', 0, 'Ruhe', 'Sleep', -1);
			IPS_SetVariableProfileAssociation('TFE3D.printState', 1, 'Druckt', 'Download', -1);
		}

		if (!IPS_VariableProfileExists('TFE3D.percentDone')) 
		{
            IPS_CreateVariableProfile('TFE3D.percentDone', 1);
			IPS_SetVariableProfileIcon('TFE3D.percentDone', 'Intensity');
			IPS_SetVariableProfileText('TFE3D.percentDone', '', ' %');
			IPS_SetVariableProfileValues('TFE3D.percentDone', 0, 100, 1);
		}
		
		$this->RequireParent("{D68FD31F-0E90-7019-F16C-1949BD3079EF}");

		$this->RegisterTimer("TimerGetPerMinute", 0, 'TFE3D_GetPerMinute($_IPS["TARGET"]);');
	}
    
    public function ApplyChanges()
	{
        parent::ApplyChanges();
		$xAxis_ID			= $this->RegisterVariableFloat("xAxisCur", "X-Achse", "", 1);
		$yAxis_ID			= $this->RegisterVariableFloat("yAxisCur", "Y-Achse", "", 2);
		$zAxis_ID			= $this->RegisterVariableFloat("zAxisCur", "Z-Achse", "", 3);
		$exTempCur_ID		= $this->RegisterVariableFloat("exTempCur", "Extruder Ist-Temperatur", "TFE3D.exTemp", 4);
		$exTempSet_ID		= $this->RegisterVariableFloat("exTempSet", "Extruder Soll-Temperatur", "TFE3D.exTemp", 5);
		$bedTempCur_ID		= $this->RegisterVariableFloat("bedTempCur", "Druckbett Ist-Temperatur", "TFE3D.bedTemp", 6);
		$bedTempSet_ID		= $this->RegisterVariableFloat("bedTempSet", "Druckbett Soll-Temperatur", "TFE3D.bedTemp", 7);
		$printState_ID		= $this->RegisterVariableInteger("printState", "Status", "TFE3D.printState", 8);
		$fileSet_ID			= $this->RegisterVariableString("fileSet", "Druckdatei", "", 9);
		$printTime_ID		= $this->RegisterVariableString("printTimeCur", "Druckzeit", "", 10);
		$printTimeLeft_ID	= $this->RegisterVariableString("printTimeLeft", "Restzeit", "", 11);
		$percentDone_ID		= $this->RegisterVariableInteger("percentDone", "Druckfortschritt", "TFE3D.percentDone", 12);
		$isOnline_ID		= $this->RegisterVariableBoolean("isOnline", "Verbindung", "~Switch", 13);
		$finishMessage_ID	= $this->RegisterVariableBoolean("finishMessage", "Nachricht bei Fertigstellung", "~Switch", 14);
		$reconnect_ID		= $this->RegisterVariableBoolean("reconnect", "Drucker neu verbinden", "~Switch", 15);

		IPS_SetIcon($xAxis_ID, "Distance");
		IPS_SetIcon($yAxis_ID, "Distance");
		IPS_SetIcon($zAxis_ID, "Distance");
		IPS_SetIcon($fileSet_ID, "Image");
		IPS_SetIcon($printTime_ID, "Clock");
		IPS_SetIcon($printTimeLeft_ID, "Hourglass");
		IPS_SetIcon($isOnline_ID, "Power");
		IPS_SetIcon($finishMessage_ID, "Mail");
		IPS_SetIcon($reconnect_ID, "Plug");

		$this->EnableAction("finishMessage");
		$this->EnableAction("reconnect");

		$this->SetTimerInterval("TimerGetPerMinute", 0);

		$ws_ID = IPS_GetInstance($this->InstanceID)["ConnectionID"];
        if($ws_ID > 0) 
		{
            $this->RegisterMessage($ws_ID, IM_CHANGESTATUS);
        }
		if($printState_ID > 0) 
		{
            $this->RegisterMessage($printState_ID, VM_UPDATE);
        }

		$wsInstance = IPS_GetInstance($ws_ID);
		if(IPS_GetInstance($ws_ID)["InstanceStatus"] == 102)
		{
			$this->SetValue("isOnline", true);
					
			$this->SendData("M155 S3"); // Send Temp 3 sek
			$this->SendData("M27 C");
		}
		else
		{
			$this->SetValue("isOnline", false);

			$this->SetValue("xAxisCur", 0);
			$this->SetValue("yAxisCur", 0);
			$this->SetValue("zAxisCur", 0);
			$this->SetValue("exTempCur", 0);
			$this->SetValue("exTempSet", 0);
			$this->SetValue("bedTempCur", 0);
			$this->SetValue("bedTempSet", 0);
			$this->SetPrintStop();
		}
    }


	public function MessageSink($time, $sender, $message, $data)
	{
		//IPS_LogMessage("MessageSink", "Message from SenderID ".$sender." with Message ".$message."\r\n Data: ".print_r($data, true));
		if($sender == IPS_GetInstance($this->InstanceID)["ConnectionID"])
		{
			if ($message == IM_CHANGESTATUS) 
			{
				if($data[0] == 102)
				{
					$this->SetValue("isOnline", true);
					
					$this->SendData("M155 S3"); // Send Temp 3 sek
					$this->SendData("M27 C");
				}
				else
				{
					$this->SetValue("isOnline", false);

					$this->SetValue("xAxisCur", 0);
					$this->SetValue("yAxisCur", 0);
					$this->SetValue("zAxisCur", 0);
					$this->SetValue("exTempCur", 0);
					$this->SetValue("exTempSet", 0);
					$this->SetValue("bedTempCur", 0);
					$this->SetValue("bedTempSet", 0);
					$this->SetPrintStop();
				}
			}
		}
		if($sender == IPS_GetObjectIDByIdent("printState", $this->InstanceID))
		{
			if($message == VM_UPDATE)
			{
				if($data[0] == 0 && $data[1] == 1 && $this->GetValue("finishMessage"))
				{
					$wfc_ID		= $this->ReadPropertyInteger("wfcID");
					$subject 	= $this->ReadPropertyString("subject");
					$message 	= $this->ReadPropertyString("message");
					$sound		= $this->ReadPropertyInteger("sound");

					switch($sound)
					{
						case 0 : $pushSound = "alarm"; break;
						case 1 : $pushSound = "bell"; break;
						case 2 : $pushSound = "boom"; break;
						case 3 : $pushSound = "buzzer"; break;
						case 4 : $pushSound = "connected"; break;
						case 5 : $pushSound = "dark"; break;
						case 6 : $pushSound = "digital"; break;
						case 7 : $pushSound = "drums"; break;
						case 8 : $pushSound = "duck"; break;
						case 9 : $pushSound = "full"; break;
						case 10 : $pushSound = "happy"; break;
						case 11 : $pushSound = "horn"; break;
						case 12 : $pushSound = "inception"; break;
						case 13 : $pushSound = "kazoo"; break;
						case 14 : $pushSound = "roll"; break;
						case 15 : $pushSound = "siren"; break;
						case 16 : $pushSound = "space"; break;
						case 17 : $pushSound = "trickling"; break;
						case 18 : $pushSound = "turn"; break;
					}
					WFC_PushNotification($wfc_ID, $subject, $message, $pushSound, $this->InstanceID);
				}
			}
		}
    }

	public function SetPrintActive()
	{
		$this->SendData("M27");
		$this->SendData("M27 C");
		$this->SetValue("printState", 1);
		$this->SetValue("printTimeCur", "0s");
		$this->SetValue("printTimeLeft", "Wird berechnet...");
		$this->SendData("M31");

		$this->SetTimerInterval("TimerGetPerMinute", 60000);
	}

	public function SetPrintStop()
	{
		$this->SetValue("printState", 0);
		$this->SetValue("fileSet", "");
		$this->SetValue("printTimeCur", "");
		$this->SetValue("printTimeLeft", "");
		$this->SetValue("percentDone", 0);
		
		$this->SetTimerInterval("TimerGetPerMinute", 0);
	}
	
	public function Test()
	{
		$this->SendData("M31");
	}

	public function GetPerMinute()
	{
		$this->SendData("M31");
		$this->SendData("M27");
	}
		
	public function ReceiveData($JSONString) 
	{
        $data = json_decode($JSONString, true);
		if($data['DataID'] == '{018EF6B5-AB94-40C6-AA53-46943E824ACF}')
		{
			//$this->SendDebug("Empfange vom Websocket", $JSONString, 0);
			preg_match_all('/X:(\d?\d?\d\.\d\d)\sY:(\d?\d?\d\.\d\d)\sZ:(\d?\d?\d\.\d\d)/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				if(!empty($matches[1][0]))
				{
					$this->SetValue("xAxisCur", floatval($matches[1][0]));
				}
				if(!empty($matches[2][0]))
				{
					$this->SetValue("yAxisCur", floatval($matches[2][0]));
				}
				if(!empty($matches[3][0]))
				{
					$this->SetValue("zAxisCur", floatval($matches[3][0]));
				}
			}

			// TEMP
			preg_match_all('/T:(\d?\d?\d\.\d\d)\s\/(\d?\d?\d\.\d\d).*B:(\d?\d\.\d\d)\s\/(\d?\d\.\d\d)/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				if(!empty($matches[1][0]))
				{
					$this->SetValue("exTempCur", floatval($matches[1][0]));
				}
				if(!empty($matches[2][0]))
				{
					$this->SetValue("exTempSet", floatval($matches[2][0]));
				}
				if(!empty($matches[3][0]))
				{
					$this->SetValue("bedTempCur", floatval($matches[3][0]));
				}
				if(!empty($matches[4][0]))
				{
					$this->SetValue("bedTempSet", floatval($matches[4][0]));
				}
			}

			// M24 - Start or Resume SD print
			preg_match_all('/M24/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				$this->SetPrintActive();
			}
			// M79 S4 Stop ?
			preg_match_all('/(M79 S4|Done\sprinting\sfile)/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				$this->SetPrintStop();
			}

			// Print bytes
			preg_match_all('/SD printing byte (\d+)\/(\d+)/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				if(!empty($matches[1][0]))
				{
					$actualData = intval($matches[1][0]);
				}
				if(!empty($matches[2][0]))
				{
					$completeData = intval($matches[2][0]);
				}
				$percentDone = (100/$completeData)*$actualData;
				
				$this->SetValue("percentDone", $percentDone);
			} 


			// Print time
			preg_match_all('/Print time:\s*((\d+d\s*)?(\d+h\s*)?(\d+m\s*)?(\d+s\s*))/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				if(!empty($matches[1][0]))
				{
					$this->SetValue("printTimeCur", $matches[1][0]);
				}
				
				if(!empty($matches[5][0]))
				{
					$seconds = intval($matches[5][0]);
				}
				if(!empty($matches[4][0]))
				{
					$seconds += intval($matches[4][0])*60;
				}
				if(!empty($matches[3][0]))
				{
					$seconds += intval($matches[3][0])*3600;
				}
				if(!empty($matches[2][0]))
				{
					$seconds += intval($matches[2][0])*86400;
				}
				
				if(isset($actualData) && $actualData >0)
				{
					$secondsLeft = ($seconds/$actualData)*($completeData-$actualData);
					$days = floor($secondsLeft / 86400); // 86400 seconds in a day
					$secondsLeft %= 86400;
					$hours = floor($secondsLeft / 3600); // 3600 seconds in an hour
					$secondsLeft %= 3600;
					$minutes = floor($secondsLeft / 60); // 60 seconds in a minute
					$seconds = $secondsLeft % 60;
					$durationLeft = "";
					if($days > 0)
					{
						$durationLeft .= $days."d ";
					}
					if($hours > 0)
					{
						$durationLeft .= $hours."h ";
					}
					if($minutes > 0)
					{
						$durationLeft .= $minutes."m ";
					}
					if($seconds > 0)
					{
						$durationLeft .= $seconds."s";
					}
					if($percentDone >=1)
					{
						$this->SetValue("printTimeLeft", $durationLeft);
					}
				}
			}

			// Full Filename
			preg_match_all('/Current file:\s*[^ ]+\s+([^ \n]+\.gcode)/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				if($this->GetValue("printState") == 0)
				{
					$this->SetPrintActive();
				}
				if(!empty($matches[1][0]))
				{
					$this->SetValue("fileSet", $matches[1][0]);
				}
			}
			// Printing not active
			preg_match_all('/Not\sSD\sprinting/', $data['Buffer'], $matches);
			if(!empty($matches[0][0]))
			{
				if($this->GetValue("printState") == 1)
				{
					$this->SetPrintStop();
				}
			}
		}  
    }
	
	public function SendData(string $value)
	{
		$data['DataID'] 			= '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
		$data['Buffer'] 			= $value."\r\n";
        	$dataJSON 				= json_encode($data);
        	$this->SendDataToParent($dataJSON);
	}
	
	public function RequestAction($ident, $value) 
	{
		switch($ident)
		{
			case "finishMessage" :
				$this->SetValue("finishMessage", $value);
			break;
			case "reconnect" :
				$this->SetValue("reconnect", false);
				$ws_ID = IPS_GetInstance($this->InstanceID)["ConnectionID"];
        		if($ws_ID > 0) 
				{
					IPS_SetProperty($ws_ID, 'Active', false);
					IPS_ApplyChanges($ws_ID);
					IPS_SetProperty($ws_ID, 'Active', true);
					IPS_ApplyChanges($ws_ID);
				}
			break;
		}
	}
}