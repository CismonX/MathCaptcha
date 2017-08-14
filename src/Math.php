<?php

namespace CismonX\CaptchaQueue;
use Acast\ {
    Config, Console
};
use CismonX\ProcConnection;
use Gregwar\Tex2png\Tex2png;

/**
 * Class Math
 * @package CismonX\CaptchaQueue
 */
class Math extends CaptchaInterface {
    /**
     * Status list
     */
    const STATUS_FORMULA = 0;
    const STATUS_RESULT = 1;
    const STATUS_INIT = -1;
    /**
     * List of loaded rules.
     * @var array
     */
    protected $_formulaList = [];
    /**
     * List of enabled rules.
     * @var array
     */
    protected $_enableList = [];
    /**
     * @var ProcConnection
     */
    protected $_connection;
    /**
     * Current status
     * @var int
     */
    protected $_status;
    /**
     * Raw formula.
     * @var string
     */
    protected $_formula;
    /**
     * Currently generated captcha image ID.
     * @var string
     */
    protected $_id;
    /**
     * Current rule.
     * @var MathRuleInterface
     */
    protected $_current;
    /**
     * Add a rule to the generator.
     *
     * @param string $name
     * @param string $namespace
     */
    function add(string $name, string $namespace = '\\CismonX\\CaptchaQueue\\Rules') {
        if (isset($this->_formulaList[$name]))
            Console::warning("Overwriting formula \"$name\".");
        $class_name = $namespace . '\\' . $name;
        if (!class_exists($class_name)) {
            Console::warning("Invalid formula \"$name\".");
            return;
        }
        $this->_formulaList[$name] = new $class_name;
        self::enable($name);
    }
    /**
     * Enable formula.
     *
     * @param string $name
     */
    function enable(string $name) {
        if (!isset($this->_formulaList[$name])) {
            Console::warning("Formula \"$name\" do not exist.");
            return;
        }
        $this->_enableList = $this->_enableList + [$name];
    }
    /**
     * Disable formula.
     *
     * @param string $name
     */
    function disable(string $name) {
        $pos = array_search($name, $this->_enableList);
        if ($pos === false)
            return;
        unset($this->_enableList[$pos]);
    }
    /**
     * {@inheritdoc}
     */
    function init() {
        $this->_status = self::STATUS_INIT;
        $this->_connection = new ProcConnection('maxima');
        $this->_connection->setArray([
            ProcConnection::TYPE => ProcConnection::PTY,
            ProcConnection::ON_MESSAGE => [$this, 'onMessage']
        ]);
        $this->_connection->run();
    }
    /**
     * Call when process output message.
     *
     * @param ProcConnection $conn
     * @param $data
     */
    function onMessage(ProcConnection $conn, $data) {
        if ($this->_status == self::STATUS_INIT)
            return;
        $data = self::_parseTex($data);
        if ($data === false)
            return;
        if ($this->_status == self::STATUS_FORMULA) {
            $this->_formula = $data;
            $this->_id = self::genPic($data);
            $this->_status = self::STATUS_RESULT;
            $conn->send($this->_current->withEval());
        } else {
            $message = $this->_current->getMessage();
            msg_send(Captcha::getQueue(), 1, [$this->_id, $message, $data]);
            if (msg_stat_queue(Captcha::getQueue())['msg_qnum'] < Config::get('CAPTCHA_CACHE_MAX')) {
                $this->generate();
            } else {
                $this->_status = self::STATUS_INIT;
            }
        }
    }
    /**
     * Randomly select one from the formula list.
     *
     * @return mixed
     */
    protected function _chooseOne() {
        return $this->_formulaList[$this->_enableList[mt_rand(0, count($this->_enableList) - 1)]];
    }
    /**
     * {@inheritdoc}
     */
    function generate() {
        $this->_status = self::STATUS_FORMULA;
        $this->_current = $this->_chooseOne();
        $this->_current->create();
        $this->_connection->send($this->_current->withoutEval());
    }
    /**
     * Generate PNG using Tex2png with TeX formatted formula.
     *
     * @param $formula
     * @return string
     */
    static function genPic($formula) {
        $id = uniqid();
        Tex2png::create($formula)->saveTo(Config::get('CAPTCHA_PIC_PATH').$id)->generate();
        self::handleImg($id);
        return $id;
    }
    /**
     * Process image to prevent captcha recognition.
     * @param $id
     */
    protected static function handleImg($id) {
        $img = new \Imagick(Config::get('CAPTCHA_PIC_PATH').$id);
        $img->swirlImage(mt_rand(15, 25));
        $img->motionBlurImage(1.5, 3, mt_rand(0, 360));
        $img->adaptiveResizeImage(Config::get('CAPTCHA_WIDTH'), Config::get('CAPTCHA_HEIGHT'), false);
        $img->addNoiseImage(\Imagick::NOISE_GAUSSIAN, \Imagick::CHANNEL_ALL);
        $img->writeImage(Config::get('CAPTCHA_PIC_PATH').$id);
    }
    /**
     * Extract content from Maxima TeX output.
     *
     * @param string $data
     * @return bool|mixed
     */
    protected static function _parseTex(string $data) {
        preg_match('{\\$\\$([\\S\\s]*?)\\$\\$}', $data, $matches);
        if (isset($matches[0])) {
            $str = str_replace(["\r", "\n"], '', $matches[1]);
            return $str;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    function destroy(){
        $this->_connection->close();
    }
}