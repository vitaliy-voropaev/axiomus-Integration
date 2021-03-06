<?php

/*  CLASSES */

namespace Axiomus;

interface SendQuery
{
    const DBG = "dbg";
    public function send($mode = "");
}

class Query implements SendQuery
{
    private $mode;
    private $uid;
    private $order;
    private $option = array();

	// Разрешенные запросы
    private $allow = array("get_price", "get_regions", "get_carry", "get_regions", "get_dpd_pickup", "get_boxberry_pickup");

    const DEMO = "DEMO";
    const LIVE = "LIVE";

    const DEMO_UID = "XXcd208495d565ef66e7dff9f98764XX";
    const DEMO_UKEY = "92";
    const DEMO_URL = "http://axiomus.ru/test/api_xml_test.php";

    const LIVE_UKEY = "";
    const LIVE_UID = "";
    const LIVE_URL = "http://axiomus.ru/hydra/api_xml.php";

    private $modeSend = self::DEMO_URL;

    function __construct($mode, $ukey="", $uid="")
    {
        $this->mode = $mode;
        $this->ukey = $ukey;
        $this->uid = $uid;
    }
    
    function setEnvironment($mode = self::DEMO)
    {
        if  (self::DEMO  == $mode)
        {
            $this->uid = self::DEMO_UID;
            $this->ukey = self::DEMO_UKEY;
            $this->modeSend = self::DEMO_URL;
            
            return true;
        }
        
        if  (self::LIVE  == $mode)
        {
            $this->uid = self::LIVE_UID;
            $this->ukey = self::LIVE_UKEY;
            $this->modeSend = self::LIVE_URL;
            
            return true;
        }
    }

    function setOrder($order)
    {
        $this->order = $order;
        $this->order->set_uid($this->uid);
    }
    
    function setOptions($name, $value)
    {
        if (!empty($name) && !empty($value))
        {
            $this->option[$name] = $value;
            return true;
        }
        
        return false;
    }
    
    function toXml()
    {
        if (self::LIVE_URL == $this->modeSend) 
        {
            if (false === array_search($this->mode, $this->allow)) 
            {
                return;
            }
        }
        
        if (empty($this->mode) || empty($this->ukey)){ return; }

        $result = "<"."?xml version='1.0' standalone='yes'?"."> <singleorder>";
        $result .=" <mode";
        
        if (!empty($this->option["type"]))
        {
            $result .= ' type="'.$this->option["type"].'" ';
        }
        
        $result .=">".$this->mode."</mode>\n";

        if ($this->order) 
        {
            $result .= "<auth ukey=\"" . $this->ukey . "\" checksum=\"" . $this->order->getChecksum() . "\" />\n";
            $result .= $this->order->toXml();
        
        } else {
	        
            $result .= "<auth ukey=\"" . $this->ukey . "\" />\n";
        }

        $result .= "</singleorder>";
        
        return $result;
    }

    function send($mode = "")
    {
        if (self::DBG == $mode)
        {
            return $this->toXml();
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->modeSend);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=".urlencode( $this->toXml() ));
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
}

class Item
{
    private $name;
    private $weight;
    private $quantity;
    private $price;
    
    function get($name)
    {
        return $this->$name;
    }

    function __construct($name, $weight, $quantity, $price) 
    {
        $this->name = $name;
        $this->weight = $weight;
        $this->quantity = $quantity;
        $this->price = (float) $price;
    }
    
    public function toXml()
    {
        return '<item name="'.$this->name.'" weight="'.$this->weight.'" quantity="'.$this->quantity.'" price="'.$this->price.'" />'."\n";
    }
}

class Items
{
    private $items = array();
    
    function add($item)
    {
        $this->items[] = $item;
    }
    
    public function toXml()
    {
        $result = "";
        $result .= "<items>"."\n";
        
        if (!empty($this->items))
        {
            foreach ($this->items as $item)
            {
                $result .= $item->toXml();
            }
        }
        
        $result .= "</items>"."\n";
        
        return $result;
    }
    
    function getCountItems()
    {
        return count($this->items);
    }
    
    function getCountQuantity()
    {
        $quantity = 0;
        
        if (empty($this->items))
        {
            return $s;
        }
        
        foreach ($this->items as $value)
        {
            $quantity += (int) $value->get("quantity");
        }
        
        return (int) $quantity;
    }
    
    function getPrice()
    {
        $price = 0;
        
        if (empty($this->items))
        {
            return $price;
        }
        
        foreach ($this->items as $value)
        {
            $sprice +=  $value->get("price") * $value->get("quantity");
        }
        
        return $price;
    }
}

class Delivset
{
	//Цена доставки для покупателя для случая Полного отказа
    private $return_price;
    //Цена доставки при превышении верхней границы стоимости 
    private $above_price;
    private $below = array();
    private $sums = array();

    private static  $instance = null;
    /**
     * @return Singleton
     */
    public static function getInstance()
    {
        if (null === self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __clone() {}
    private function __construct() {}

    function setReturnPrice($return_price)
    {
        $this->return_price = $return_price;
    }

    function setAbovePrice($above_price)
    {
        $this->above_price = $above_price;
    }

    function setOptions($below_sum, $price)
    {
        $this->below[(float) $below_sum] = (float) $price;
        ksort($this->below);
        $this->sums = array_keys($this->below);
    }

    function getPrice($price)
    {
        $min = min($this->sums);
        $max = max($this->sums);
        
        if ($price <= $min)
        {
            return $this->below[$min];
        }
        
        if ($price > $max)
        {
            return $this->above_price;
        }

        $prev = array_shift($this->sums);
        
        while (count($this->sums))
        {
            $this_el = $this->sums[0];
            
            if ($price >= $prev && $price <= $this_el)
            {
                return $this->below[$this_el];
            }
            
            $prev = array_shift($this->sums);
        }
        
        return $price;
    }

    function toXml()
    {
        $result = '<delivset ';
        
        if ($this->return_price)
        {
            $result .= 'return_price="'.$this->return_price.'" ';
        }
        
        if ($this->above_price)
        {
            $result .= 'above_price="'.$this->above_price.'" ';
        }
        
        $result .= '>'."\n";

        foreach ($this->below as $below_sum => $price)
        {
            $result .= '<below below_sum="'.$below_sum.'" price="'.$price.'" />'."\n";
        }
        
        $result .= '</delivset>'."\n";
        
        return $result;
    }
}

class Order
{
    private $option = array();
    private $items;
    private $uid;
    private $contacts;
    private $description;
    private $services = array();
    private $delivset;
    private $address = array();

    function setAddress($name, $value)
    {
        if (!empty($name) && !empty($value))
        {
            $this->address[$name] = $value;
        }
        
        return false;
    }

    function setOptions($name, $value)
    {
        if (!empty($name) && !empty($value))
        {
            if ("contacts" == $name)
            {
                $this->contacts = $value;
                return true;
            }
            
            if ("description" == $name)
            {
                $this->description = $value;
                return true;
            }
            
            if ("services" == $name)
            {
                $this->services = $value;
                return true;
            }
            
            $this->option[$name] = $value;
            return true;
        }
        
        return false;
    }
    
    function setItems($items)
    {
        $this->items = $items;
    }
    
    function toXml()
    {
        $result = "<order ";
        
        foreach($this->option as $key=>$value){
            $result .= " ".$key."=\"".str_replace("\"",",",$value)."\" "."\n";
        }
        
        $result .= " >";

        if (!empty($this->address))
        {
            $result .= '<address ';
            
            foreach($this->address as $key=>$value)
            {
                $result .= $key.'="'.$value.'" ';
            }
            
            $result .= ' />';
        }

        $result .= '<contacts>'.$this->contacts.'</contacts>'."\n";
        $result .= '<description>'.$this->description.'</description>'."\n";
        
        if (!empty($this->services))
        {
            $result .= '<services ';
            
            foreach ($this->services as $key=>$value)
            {
                $result .= $key.'="'.$value.'" ';
            }
            
            $result .= '/>'."\n";
        }

        $result .= $this->items->toXml();
        
        if ($this->delivset)
        {
            $result .= $this->delivset->toXml();
        }

        $result .= "</order>"."\n";
        return $result;
    }
    
    function set_uid($uid)
    {
        $this->uid = $uid;
    }
    
    function getChecksum()
    {
        return md5($this->uid.'u'.$this->items->getCountItems().$this->items->getCountQuantity());
    }
    
    function valid()
    {
        return true;
    }
}
