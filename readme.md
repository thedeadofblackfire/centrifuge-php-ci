Centrifuge PHP Library (CI version)
===================

This is a CodeIgniter version of the PHP library.
You can find full documentation on that project inside the library class.

How to use
----------

    $this->load->library('centrifuge');
    $this->centrifuge->trigger('publish', array('channel' => 'channel_name', 'data' => array('label' => 'Hello World')), $debug=false);
    or
    $this->centrifuge->publish('channel_name', array('label' => 'Hello World'), $debug=false);
    
    $this->centrifuge->presence('channel_name', $debug=false);

