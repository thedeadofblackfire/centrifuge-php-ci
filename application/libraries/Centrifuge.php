<?php

/* 
    Centrifuge PHP Library
	/////////////////////////////////
	PHP library for the Centrifuge Admin API.

	See the README for usage information: https://github.com/FZambia/centrifuge

	Copyright 2014. Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php

	Contributors:
		+ Nicolas Thomas (http://github.com/thedeadofblackfire)

*/

class CentrifugeException extends Exception
{
}

class CentrifugeInstance {
	
	private static $instance = null;
	private static $project_id	= '';
	private static $secret_key	= '';
    private static $host = '';
    private static $port = '';
	
	private function __construct() { }
	private function __clone() { }
	
	public static function get_centrifuge()
	{
		if (self::$instance !== null) return self::$instance;

		self::$instance = new Centrifuge(
			self::$project_id, 
			self::$secret_key,
            self::$host,
            self::$port
		);

		return self::$instance;
	}
}

class Centrifuge
{
	public static $VERSION = '1.0.0';

	private $settings = array();
	private $logger = null;

	/**
	* PHP5 Constructor. 
	* 
	* Initializes a new Centrifuge instance with app ID, secret and channel. 
	* You can optionally turn on debugging for all requests by setting debug to true.
	* 
	* @param string $project_id
	* @param string $secret_key
	* @param bool $debug [optional]
	* @param string $host [optional]
	* @param int $port [optional]
	* @param int $timeout [optional]
	*/
	public function __construct( $project_id = '', $secret_key = '', $host = 'http://centrifuge.example.com', $port = '8000', $debug = false, $timeout = 5 )
	{
		// Check compatibility, disable for speed improvement
		$this->check_compatibility();        

        // Get Codeigniter instance, and config data
		$this->CI = get_instance();
		$this->CI->load->config('centrifuge');

		// Setup defaults
		$ciCentrifugeHost = $this->CI->config->item('centrifuge_host');
		if(!empty($ciCentrifugeHost)) {
			$this->settings['server'] 	= $ciCentrifugeHost;
		} else {
			$this->settings['server']	= $host;
		}
		$ciCentrifugePort = $this->CI->config->item('centrifuge_port');
		if(!empty($ciCentrifugePort)) {
			$this->settings['port']		= $ciCentrifugePort;
		} else {
			$this->settings['port']		= $port;
		}
		
		$this->settings['secret_key'] 	= $this->CI->config->item('centrifuge_secret_key');
		$this->settings['project_id'] 	= $this->CI->config->item('centrifuge_project_id');
		$ciCentrifugeUrl = $this->CI->config->item('centrifuge_url');
		if(!empty($ciCentrifugeUrl)) {
			$this->settings['url']		= $ciCentrifugeUrl;
		} else {
			$this->settings['url']		= '/api/' . $this->settings['project_id'];
		}
		$ciCentrifugeDebug = $this->CI->config->item('centrifuge_debug');
		if(!empty($ciCentrifugeDebug)) {
			$this->settings['debug']	= $ciCentrifugeDebug;
		} else {
			$this->settings['debug']	= $debug;
		}
		$ciCentrifugeTimeout = $this->CI->config->item('centrifuge_timeout');
		if(!empty($ciCentrifugeTimeout)) {
			$this->settings['timeout']	= $ciCentrifugeTimeout;
		} else {
			$this->settings['timeout']	= $timeout;
		}
        	
        $this->settings['encryption'] = 'md5';
	}

	/**
	 * Set a logger to be informed of interal log messages.
	 */
	public function set_logger( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Log
	 */
	private function log( $msg ) {
		if( is_null( $this->logger ) == false ) {
			$this->logger->log( 'Centrifuge: ' . $msg );
		}
	}

	/**
	 * Check if the current PHP setup is sufficient to run this class
	 */
	private function check_compatibility()
	{
		if ( ! extension_loaded( 'curl' ) || ! extension_loaded( 'json' ) )
		{
			throw new CentrifugeException('There is missing dependant extensions - please ensure both cURL and JSON modules are installed');
		}

		if ( ! in_array( 'md5', hash_algos() ) )
		{
			throw new CentrifugeException('md5 appears to be unsupported - make sure you have support for it, or upgrade your version of PHP.');
		}

	}
	
	/**
	 * Utility function used to create the curl object with common settings
	 */
	private function create_curl($s_url, $post_params = array() )
	{

		$full_url = $this->settings['server'];
        if ($this->settings['port'] != '80') $full_url .= ':' . $this->settings['port'];
        $full_url .= $s_url ;

		$this->log( 'curl_init( ' . $full_url . ' )' );
		
        $post_value = Centrifuge::array_implode( '=', '&', $post_params );
             
		# Set cURL opts and execute request
		$ch = curl_init();
		if ( $ch === false )
		{
			throw new CentrifugeException('Could not initialize cURL!');
		}

		curl_setopt( $ch, CURLOPT_URL, $full_url );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array ( "Content-type: application/x-www-form-urlencoded" ) );	
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->settings['timeout'] );
		
        $this->log( 'trigger POST: ' . $post_value );

		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_value );
        
		return $ch;
	}

	/**
	 * Utility function to execute curl and create capture response information.
	 */
	private function exec_curl( $ch ) {
		$response = array();

		$response[ 'body' ] = curl_exec( $ch );
		$response[ 'status' ] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$this->log( 'exec_curl response: ' . print_r( $response, true ) );

		curl_close( $ch );

		return $response;
	}
		
	/**
	 * Implode an array with the key and value pair giving
	 * a glue, a separator between pairs and the array
	 * to implode.
	 * @param string $glue The glue between key and value
	 * @param string $separator Separator between pairs
	 * @param array $array The array to implode
	 * @return string The imploded array
	 */
	public static function array_implode( $glue, $separator, $array ) {
			if ( ! is_array( $array ) ) return $array;
			$string = array();
			foreach ( $array as $key => $val ) {
					if ( is_array( $val ) )
							$val = implode( ',', $val );
					$string[] = "{$key}{$glue}{$val}";

			}		 
			return implode( $separator, $string );
	}

   /**
	* Trigger an event by providing event name and payload. 
	* Optionally provide a socket ID to exclude a client (most likely the sender).
	*
	* @param string $method
    * @param array $params
	* @param bool $debug [optional]
	* @return bool|string
	*/
    public function trigger( $method, $params, $debug = false)
	{        
        $s_url = $this->settings['url'];		
        
        $event = array("method" => $method, "params" => $params);
        $event_encoded = json_encode( $event );
              
		$post_params = array();	
		$post_params[ 'data' ] = $event_encoded;
		$post_params[ 'sign' ] = $this->socket_auth($event_encoded);

        $ch = $this->create_curl( $s_url, $post_params );

		$response = $this->exec_curl( $ch );

		if ( $response[ 'status' ] == 200)
		{
            if ($debug == true || $this->settings['debug'] == true ) {
                //return $response;
            }			
            $info = json_decode( $response[ 'body' ], true );
            if (is_array($info)) $info = $info[0];
            $info['status'] = $response[ 'status' ];           
            return $info;
		}	
		else
		{
			return false;
		}
  
	}
    
    /**
	 * Send a message into channel of namespace
	 * 
	 * @param string $channel
	 * @param array $data Event data
     * @param bool $debug [optional]
	 * @return bool|string
	 */
    public function publish($channel, $data, $debug = false)
    {
        return $this->trigger('publish', array("channel" => $channel, "data" => $data), $debug);
    }
    
    /**
	 * Unsubscribe user with certain ID from channel
	 * 
	 * @param string $channel
     * @param string $user ID
     * @param bool $debug [optional]
	 * @return bool|string
	 */
    public function unsubscribe($channel, $user, $debug = false)
    {
        return $this->trigger('unsubscribe', array("channel" => $channel, "user" => $user), $debug);
    }
    
    /**
	 * Disconnect user by user ID
	 * 
     * @param string $user ID
     * @param bool $debug [optional]
	 * @return bool|string
	 */
    public function disconnect($user, $debug = false)
    {
        return $this->trigger('disconnect', array("user" => $user), $debug);
    }
    
    /**
	 * Get channel presence information (all clients currently subscribed on this channel)
	 * 
	 * @param string $channel
     * @param bool $debug [optional]
	 * @return bool|string
	 */
    public function presence($channel, $debug = false)
    {
        return $this->trigger('presence', array("channel" => $channel), $debug);
    }
    
    /**
	 * Get channel history information (list of last messages sent into channel)
	 * 
	 * @param string $channel
     * @param bool $debug [optional]
	 * @return bool|string
	 */
    public function history($channel, $debug = false)
    {
        return $this->trigger('history', array("channel" => $channel), $debug);
    }
    
    /**
 	 * Creates a socket signature
  	 * 
	 * @param string $encoded_data (data json string)
	 * @return string
	 */
	public function socket_auth( $encoded_data )
	{		
		$signature = hash_hmac($this->settings['encryption'], $this->settings['project_id'] . $encoded_data, $this->settings['secret_key'], false );

		return $signature;
	}

    /**
	 * Creates a client token signature
     * When client from browser wants to connect to Centrifuge he must send his
     * user ID and ID of project. To validate that data we use HMAC to build
     * token.
	 * 
	 * @param int $user
     * @param int $timestamp
	 * @param string $custom_data json valid
	 * @return string
	 */
	public function get_client_token($user, $timestamp, $custom_data = false )
	{
		if($custom_data == true)
		{
			$token = hash_hmac($this->settings['encryption'], $this->settings['project_id'] . $user . $timestamp . $custom_data, $this->settings['secret_key'], false );
		}
		else
		{
			$token = hash_hmac($this->settings['encryption'], $this->settings['project_id'] . $user . $timestamp, $this->settings['secret_key'], false );
		}

        return $token;
	}
}
