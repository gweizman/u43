<?php
    namespace talnet;

    use Exception;

    class Application {
        public $_name, $_key;

        public function __construct($name, $key) {
            $this->_name = $name;
            $this->_key = $key;

            return $this;
        }

        public function send($request, $username = NULL, $password = NULL)
        {
            // Sends the request to the server through the TCP connection
            // Must be called after U443::connect()
            error_reporting(E_ALL);
            if ($username == NULL) {
                if (isset($_SESSION['username'])) {
                    $user = $_SESSION['username'];
                    $pass = $_SESSION['pass'];
                } else {
                    $user = "Anonymous";
                    $pass = Utilities::encrypt("");
                }
            } else {
                $user = $username;
                $pass = $password;
            }
            $address = "localhost";
            //$port = 4850;
            $port = 4855;
            if (isset($_SESSION['dev']) and $_SESSION['dev'] == true) {
                $port = 4855;
            }

            /* Create a TCP/IP socket. */
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
            $result = socket_connect($socket, $address, $port) or die("socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)));

            $challenge = trim(socket_read($socket, 2048));

            $request = array(
                "RequesterCredentials" => array(
                    "appName" => $this->_name,
                    "appKey" => Utilities::challenge(md5($this->_key), $challenge),
                    "username" => $user,
                    "password" => Utilities::challenge($pass, $challenge)
                ),
                "RequestInfo" => $request["RequestInfo"],
                "RequestData" => $request["RequestData"]
            );
            $_SESSION['last_request'] = $request;
            $message = json_encode($request, JSON_UNESCAPED_UNICODE);
            $_SESSION['last_request_json'] = $message;
            socket_write($socket, $message, strlen($message));
            $output = '';
            while (($buffer = socket_read($socket, 2048, PHP_BINARY_READ)) != '') {
                $output = $output . $buffer;
            }
            socket_close($socket);
            $output = stripslashes($output);
            $_SESSION['raw_output'] = $output;
            $decode = json_decode($output);
            $_SESSION['last_response'] = $decode;
            $_SESSION['json_error'] = json_last_error();
            if ($decode->Status != 1) {
                throw new Exception($decode->Message);
            }
            return $decode->Data;
        }
    }
?>