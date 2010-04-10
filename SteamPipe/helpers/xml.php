<?
function process_xml($input, $recurse = false)
{
    $data = ((!$recurse) && is_string($input))? simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA): $input;
       
    if ($data instanceof SimpleXMLElement)
    {
        $data = (array) $data;
        if(empty($data))
        {
            $data = '';
        }
    }
        
    if (is_array($data)) 
    {
        foreach ($data as &$item) 
        {
            $item = process_xml($item, true); //edit by reference
        }
    }

    return $data;
}