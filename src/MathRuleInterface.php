<?php

namespace CismonX\CaptchaQueue;

abstract class MathRuleInterface {
    /**
     * Create a new Captcha.
     */
    abstract function create();
    /**
     * Hint message for the captcha.
     * @return string
     */
    abstract function getMessage();
    /**
     * @return string : Formula which will be used for evaluation.
     */
    abstract function withEval();
    /**
     * @return string : Formula which will be used for display.
     */
    abstract function withoutEval();
}