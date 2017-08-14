# Readme

This library is used for generating mathematical captchas.

## 1. Requirements

* Libraries listed in composer.json.
* The command line version of [Maxima](http://maxima.sourceforge.net/). It is recommended to use [SBCL](http://www.sbcl.org/) as Maxima's common lisp implementation for better performance.
* [LaTeX](https://www.latex-project.org/). It can be easily installed using package managers like yum, apt, etc.
* PHP compiled with **PHP_CAN_DO_PTS** enabled. (I'm not sure why Maxima has to allocate a PTY)

## 2. Rules

You have to write your own rules for generating mathematical captcha. Write a class which extends `CismonX\CaptchaQueue\MathRuleInterface`, and add it to the generator using the `add()` method.

Once a rule is added, it's automatically marked as enabled. You can disable or enable any rules using methods `disble() ` and `enable()` during runtime.

[Here](https://github.com/CismonX/PolyIntegral) is an example.