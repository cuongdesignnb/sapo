<?php

namespace ZKLib;

use \DateTime;

class ZKLib {
	const USHRT_MAX = 65535;
	const CMD_CONNECT = 1000;
	const CMD_EXIT = 1001;
	const CMD_ENABLEDEVICE = 1002;
	const CMD_DISABLEDEVICE = 1003;
	const CMD_TESTVOICE = 1017;
	const CMD_ACK_OK = 2000;
	const CMD_ACK_ERROR = 2001;
	const CMD_ACK_DATA = 2002;
	const CMD_ACK_UNAUTH = 2005;
	const CMD_PREPARE_DATA = 1500;
	const CMD_DATA = 1501;
	const CMD_USER_WRQ = 8;
	const CMD_USERTEMP_RRQ = 9;
	const CMD_DEVICE = 11;
	const CMD_ATTLOG_RRQ = 13;
	const CMD_CLEAR_DATA = 14;
	const CMD_CLEAR_ATTLOG = 15;
	const CMD_DELETE_USER = 18;
	const CMD_CLEAR_ADMIN = 20;
	const CMD_GET_TIME = 201;
	const CMD_SET_TIME = 202;
	const CMD_VERSION = 1100;
	const CMD_GET_FREE_SIZES = 50;
	const CMD_ENABLE_CLOCK = 57;
	const CMD_WRITE_LCD = 66;
	const CMD_CLEAR_LCD = 67;
	const LEVEL_USER = 0;
	const LEVEL_ADMIN = 14;
	const DEVICE_GENERAL_INFO_STRING_LENGTH = 184;

	/**
	 * @var $socket
	 */
	private $socket;

	/**
	 * @var string
	 */
	private $ip;

	/**
	 * @var integer
	 */
	private $port;

	/**
	 * @var array
	 */
	private $timeout = array('sec'=>30,'usec'=>500000);

	/** @var  string */
	private $data;

	/** @var  integer */
	private $session_id;

	/** @var integer */
	private $reply_id;

	private $response_code;
	private $checksum;

	public function __construct($ip = '', $port = 4370)
	{
		$this->ip = $ip;
		$this->port = $port;
	}

	/**
	 * @return string
	 */
	public function getIp()
	{
		return $this->ip;
	}

	/**
	 * @param string $ip
	 * @return ZkSocket
	 */
	public function setIp($ip)
	{
		$this->ip = $ip;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * @param int $port
	 * @return ZkSocket
	 */
	public function setPort($port)
	{
		$this->port = $port;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getTimeout()
	{
		return $this->timeout;
	}

	/**
	 * @param array $timeout
	 * @return ZkSocket
	 */
	public function setTimeout($timeout)
	{
		$this->timeout = $timeout;
		return $this;
	}

	private function createHeader($command, $command_string, $chksum=0) {
		$buf = pack('SSSS', $command, $chksum, $this->session_id, $this->reply_id).$command_string;
		$this->reply_id += 1;
		if ($this->reply_id >= self::USHRT_MAX) {
			$this->reply_id -= self::USHRT_MAX;
		}
		$buf = pack('SSSS', $command, $this->createCheckSum($buf), $this->session_id, $this->reply_id);
		return $buf.$command_string;
	}

	protected function createCheckSum($buffer){
		$checksum = 0;
		if (strlen($buffer)%2){
			$buffer .=chr(0);
		}
		foreach (unpack('v*', $buffer) as $data){
			$checksum += $data;
			if ($checksum > self::USHRT_MAX){
				$checksum -= self::USHRT_MAX;
			}
		}
		$checksum = -$checksum - 1;
		while ($checksum < 0){
			$checksum += self::USHRT_MAX;
		}
		return ($checksum & self::USHRT_MAX);
	}

	function checkValid($reply, $extraResponses = null) {
		/*Checks a returned packet to see if it returned CMD_ACK_OK, indicating success*/
		if ($extraResponses){
			return in_array($this->response_code, array_merge([self::CMD_ACK_OK], $extraResponses));
		}
		return $this->response_code == self::CMD_ACK_OK;
	}

	public function connect()
	{
		$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, $this->timeout);
		// Increase buffer for large UDP packets (Windows often needs this)
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 8192);
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, 8192);

		$this->reply_id = (-1 + self::USHRT_MAX);
		return $this->execute(self::CMD_CONNECT, null, [self::CMD_ACK_UNAUTH]);
	}

	public function disconnect()
	{
		if($this->socket) {
			$this->execute(self::CMD_EXIT);
			socket_close($this->socket);
		}
	}

	public function enable(){
		return $this->execute(self::CMD_ENABLEDEVICE);
	}

	public function disable(){
		return $this->execute(self::CMD_DISABLEDEVICE);
	}

	public function testVoice(){
		return $this->execute(self::CMD_TESTVOICE);
	}

	private function execute($command, $command_string = null, $extraResponses = array())
	{
		$buf = $this->createHeader($command, $command_string);
		socket_sendto($this->socket, $buf, strlen($buf), 0, $this->ip, $this->port);

		$bytes = socket_recvfrom($this->socket, $this->data, 4096, 0, $this->ip, $this->port);
		if ($bytes === false){
			throw new \RuntimeException(socket_strerror(socket_last_error()));
		}

		$this->response_code = $this->getResponseCode();
		$this->session_id = $this->getSessionId();
		$this->reply_id = $this->getReplyId();

		if ($this->checkValid($bytes, $extraResponses)) {
			if ($command_string){
				return preg_replace('/^'.preg_quote($command_string, '/').'=/', '', substr( $this->data, 8 ));
			}
			return $bytes;
		}

		throw new \RuntimeException($this->getResponseMessage());
	}

	private function getResponseCode(){
		$data = unpack('vresponse', $this->data);
		return $data['response'];
	}

	private function getSessionId(){
		$data = unpack('vsession_id', substr($this->data, 4));
		return $data['session_id'];
	}

	private function getReplyId(){
		$data = unpack('vreply_id', substr($this->data, 6));
		return $data['reply_id'];
	}

	private function getResponseMessage(){
		switch($this->response_code){
			case self::CMD_ACK_OK: return 'Success';
			case self::CMD_ACK_ERROR: return 'Command Failed';
			case self::CMD_ACK_DATA: return 'Data Packet';
			case self::CMD_ACK_UNAUTH: return 'Unauthorized';
			default: return 'Unknown Error (code: '.$this->response_code.')';
		}
	}

	public function getVersion(){
		return $this->execute(self::CMD_VERSION);
	}

	public function getDeviceName(){
		return $this->execute(self::CMD_DEVICE, 'NAME');
	}

	public function getPlatform(){
		return $this->execute(self::CMD_DEVICE, 'Platform');
	}

	public function getOS(){
		return $this->execute(self::CMD_DEVICE, 'OS');
	}

	public function getFMVersion(){
		return $this->execute(self::CMD_DEVICE, 'FMVersion');
	}

	public function getWorkCode(){
		return $this->execute(self::CMD_DEVICE, 'WorkCode');
	}

	public function getSerialNumber(){
		return $this->execute(self::CMD_DEVICE, 'SN');
	}

	public function getDeviceTime(){
		$data = $this->execute(self::CMD_GET_TIME);
		$dateTime = $this->decodeTime(unpack('Vtime', $data)['time']);
		return $dateTime;
	}

	public function setDeviceTime(DateTime $dateTime){
		$data = $this->encodeTime($dateTime);
		return $this->execute(self::CMD_SET_TIME, pack('V', $data));
	}

	public function getAttendance()
	{
		$this->execute(self::CMD_ATTLOG_RRQ, null, [self::CMD_ACK_DATA, self::CMD_PREPARE_DATA]);

		$attData = '';
		$flags = 0;
		if (defined('MSG_WAITALL') && stripos(PHP_OS_FAMILY, 'Windows') === false) {
			$flags = MSG_WAITALL;
		}
		do {
			$size = socket_recvfrom($this->socket, $data, 4096, $flags, $this->ip, $this->port);
			if ($size === false) {
				throw new \RuntimeException(socket_strerror(socket_last_error()));
			}
			$attData .= $data;
		} while ($size > 0 && $size != 8);
		$attData = substr($attData, 12);
		$attData = substr($attData, 0, -8);

		$result = array();
		if ($attData){
			foreach (str_split($attData, 8) as $attInfo){
				if (strlen($attInfo) < 8) {
					continue;
				}
				$data = unpack('vuserId/Ctype/Cstatus/Vtime', $attInfo);
				$dateTime = $this->decodeTime($data['time']);
				$result[] = new Attendance(
					$data['userId'],
					$dateTime,
					$data['type'],
					$data['status']
				);
			}
		}

		return $result;
	}

	private function getPrepareDataSize()
	{
		$response = unpack('vcommand/vchecksum/vsession_id/vreply_id/vsize', $this->data);

		return ( $response['command'] == self::CMD_PREPARE_DATA ) ? $response['size'] : false;
	}

	protected function func_removeAccents($s){
		$s = preg_replace('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]#u', '', $s);
		$s = strtr($s, '`\'"^~', "\x01\x02\x03\x04\x05");
		if (ICONV_IMPL === 'glibc') {
			$s = @iconv('UTF-8', 'WINDOWS-1250//TRANSLIT', $s); // intentionally @
			$s = strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2"
				. "\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff",
					"aaaaaacceeeeiiiidnoooooouuuuyb".
					"aaaaaacceeeeiiiidnoooooouuuuyby".
					"AAAAAACCEEEEIIIIDNOOOOOOUUUUYB".
					"AAAAAACCEEEEIIIIDNOOOOOOUUUUYBY");
			$s = strtr($s, "\x01\x02\x03\x04\x05", "`\'\"^~");
		}
		return $s;
	}

	// NOTE: The rest of the original ZKLib methods (users, templates, etc.) are not required for attendance sync.
	// This minimal patched class keeps compatibility for getAttendance, device info, and time sync.

	private function decodeTime($time)
	{
		$second = $time % 60;
		$time = (int)($time / 60);
		$minute = $time % 60;
		$time = (int)($time / 60);
		$hour = $time % 24;
		$time = (int)($time / 24);
		$day = $time % 31 + 1;
		$time = (int)($time / 31);
		$month = $time % 12 + 1;
		$time = (int)($time / 12);
		$year = $time + 2000;
		return new DateTime($year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second);
	}

	private function encodeTime(DateTime $dateTime)
	{
		$year = (int)$dateTime->format('Y');
		$month = (int)$dateTime->format('n');
		$day = (int)$dateTime->format('j');
		$hour = (int)$dateTime->format('G');
		$minute = (int)$dateTime->format('i');
		$second = (int)$dateTime->format('s');
		return (($year - 2000) * 12 * 31 + ($month - 1) * 31 + ($day - 1))
			* 24 * 60 * 60 + $hour * 60 * 60 + $minute * 60 + $second;
	}

	public function getFreeSize(){
		$data = $this->execute(self::CMD_GET_FREE_SIZES);
		return new FreeSize($data);
	}

	public function getDeviceGeneralInfo(){
		$data = $this->execute(self::CMD_DEVICE, 'GeneralInfo');
		return new DeviceInfo($data);
	}

	public function getUserTemplate(){
		return $this->execute(self::CMD_USERTEMP_RRQ);
	}

	public function getUsers(){
		$users = $this->execute(self::CMD_USER_WRQ);
		$users = substr($users, 4);

		$results = array();
		foreach(str_split($users, 28) as $user){
			if (strlen($user) < 28) {
				continue;
			}
			$data = unpack('vuid/Crole/a8password/a8name/a8card/a8group', $user);
			$results[] = new User($data['uid'], trim($data['name']), $data['role'], $data['password'], $data['card'], $data['group']);
		}
		return $results;
	}

	public function clearData(){
		return $this->execute(self::CMD_CLEAR_DATA);
	}

	public function clearAttendance(){
		return $this->execute(self::CMD_CLEAR_ATTLOG);
	}

	public function clearAdmins(){
		return $this->execute(self::CMD_CLEAR_ADMIN);
	}

	public function writeLcd($line, $message){
		$message = str_pad($message, 16);
		$message = utf8_decode(substr($message, 0, 16));
		return $this->execute(self::CMD_WRITE_LCD, pack('vCa' . strlen($message), $line, 0x0, $message));
	}

	public function clearLcd(){
		return $this->execute(self::CMD_CLEAR_LCD);
	}

	public function enableClock($flashSeconds){
		return $this->execute(self::CMD_ENABLE_CLOCK, pack('C', $flashSeconds ? 0x01 : 0x00));
	}
}
