<?php

use App\Util\Strings;
use Phalcon\Config\Adapter\Ini;
use Phalcon\Crypt;
use Phalcon\Mvc\Model;
use Phalcon\Validation;

/**
 * Class Note
 *
 * @property string txt
 * @property DateTime till
 * @property string txtDecrypted
 */
class Note extends Model
{
    public $id;
    public $txt;
    protected $till;
    public $salt;
    public $readlimit;
    public $pwd;
    public $pwdConfirm;
    public $encrypted;
    public $link;

    /**
     * @var Ini
     */
    protected $cfg;

    public function initialize()
    {
        $this->cfg = $this->getDI()->get('config');
        $this->setSource('store');
        $this->setup([
            'notNullValidations' => false,
        ]);
    }

    public function getTill(): DateTime
    {
        $dt = null;
        try {
            $dt = new DateTime($this->till, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        } catch (Exception $e) {
        }
        return $dt;
    }

    public function setTill(DateTime $dt)
    {
        $maxDt = new DateTime('+10 days');
        $maxDt->setTimezone(new DateTimeZone('UTC'));

        $dt->setTimezone(new DateTimeZone('UTC'));

        $this->till = (($dt > $maxDt) ? $maxDt : $dt)->format('Y-m-d H:i:s');
    }

    /**
     * @throws Exception
     */
    public function beforeValidationOnCreate()
    {
        if (empty($this->till)) {
            $this->till = gmdate('Y-m-d H:i:s', strtotime('+10 days'));
        }
        $this->link = Strings::randomB36($this->cfg->path('app.link_length'));
        $this->salt = Strings::randomHex();
        $this->encrypted = !empty($this->pwd);
    }


    public function beforeCreate()
    {
        $this->encrypt();
    }

    /**
     * @return false
     */
    public function validation(): bool
    {
        $validator = new Validation;

        $validator->add(
            'txt',
            new Validation\Validator\PresenceOf([
                'message' => 'Note content should not be empty.'
            ])
        );

        if (strlen($this->pwd) > 0) {
            $pwdMin = $this->cfg->path('app.min_pwd_length');
            $pwdMax = $this->cfg->path('app.max_pwd_length');

            $ruls = [
                new Validation\Validator\PresenceOf([
                    'message' => 'Password should not be empty.'
                ]),
                new Validation\Validator\StringLength([
                    'min' => $pwdMin,
                    'messageMinimum' => "Minimum password length is {$pwdMin}.",
                    'max' => $pwdMax,
                    'messageMaximum' => "Maximum password length is {$pwdMax}.",
                ])
            ];

            if (!$this->id) {
                $ruls[] = new Validation\Validator\Confirmation([
                    'with' => 'pwdConfirm',
                    'message' => 'Passwords should match.'
                ]);
            }

            $validator->rules('pwd', $ruls);
        }

        return $this->validate($validator);
    }

    /**
     * @param int $mode 0:encrypt; 1:decrypt
     * @return string|null
     * @throws Crypt\Mismatch
     * @throws Exception
     */
    protected function _crypt(int $mode = 0): ?string
    {
        $txt = null;
        /**
         * @var Crypt $crypt
         */
        $crypt = $this->getDI()->get('crypt');
        $pwd = $this->getPwdHash();

        if ($mode === 1) {
            $txt = $crypt->decryptBase64($this->txt, $pwd);
        } else if ($mode === 0) {
            $txt = $crypt->encryptBase64($this->txt, $pwd);
        } else {
            throw new Exception("Wrong mode");
        }
        return $txt;
    }

    /**
     * @return string|null
     * @throws Crypt\Mismatch
     */
    public function getTxtDecrypted(): ?string
    {
        return $this->_crypt(1);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    protected function encrypt()
    {
        $this->txt = $this->_crypt();
    }

    /**
     * @throws Crypt\Mismatch
     */
    protected function decrypt()
    {
        $this->txt = $this->_crypt(1);
    }

    protected function getPwdHash()
    {
        $pwd = empty($this->pwd) ? $this->getDI()->get('config')->path('app.default_pwd') : $this->pwd;
        return Strings::keyDerivation($pwd, $this->salt);
    }
}
