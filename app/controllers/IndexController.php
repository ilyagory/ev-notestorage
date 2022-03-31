<?php

use App\Util\Arr;
use App\Util\NotFoundException;
use Phalcon\Config\Adapter\Ini;
use Phalcon\Http\ResponseInterface;
use Phalcon\Logger\AdapterInterface;
use Phalcon\Mvc\Controller;
use App\Util\HttpException as UtilHttpException;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\View;

/**
 * @property AdapterInterface log
 * @property Ini config
 */
class IndexController extends Controller
{
    /**
     * @return ResponseInterface|View
     * @throws NotFoundException|Exception
     */
    public function indexAction()
    {
        $validation = new Arr;
        $note = new Note;

        if ($this->request->isPost()) {
            if (!$this->security->checkToken()) throw new NotFoundException;

            $note->readlimit = $this->request->getPost('readlimit', 'int!');
            $note->txt = $this->request->getPost('txt', 'text');
            $note->pwd = $this->request->getPost('pwd');
            $note->pwdConfirm = $this->request->getPost('pwdConfirm');

            try {
                $note->till = new DateTime($this->request->getPost('till', ['string', 'trim']));
            } catch (Exception $e) {
            }

            if ($note->save()) {
                $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'link',
                    'params' => [
                        $this->url->get(['for' => 'app.show', 'link' => $note->link])
                    ]
                ]);
                return;
            } else {
                foreach ($note->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            }
        }

        return $this->view->setVars([
            'note' => $note,
            'validation' => $validation,
            'action' => $this->url->get(['for' => 'app.main']),
            'tokenKey' => $this->security->getTokenKey(),
            'tokenValue' => $this->security->getToken(),
            'maxPwdLength' => $this->config->path('app.max_pwd_length', 8),
            'minPwdLength' => $this->config->path('app.min_pwd_length', 4),
            'maxReadlimit' => $this->config->path('app.max_readlimit', 10),
            'maxTill' => new DateTime('+' . $this->config->path('app.max_till', '1 month')),
            'minTill' => new DateTime('now'),
        ]);
    }

    public function linkAction(string $lnk)
    {
        $this->view->setVar('link', $lnk);
    }

    /**
     * @param string $lnk
     * @return ResponseInterface|View
     * @throws NotFoundException
     */
    public function showAction(string $lnk)
    {
        /**
         * @var Note $note
         */
        $note = Note::findFirst(['link = ?0', 'bind' => [$lnk]]);
        if (!$note) throw new NotFoundException;

        $action = $this->url->get(['for' => 'app.show', 'link' => $lnk]);
        $txt = '';
        $validation = new Arr;
        $enc = $note->encrypted;

        if ($this->request->isPost()) {
            if (!$note->encrypted) throw new NotFoundException;

            $note->pwd = $this->request->getPost('pwd');

            if ($note->validation()) {
                try {
                    $txt = $note->txtDecrypted;
                    $enc = false;
                } catch (Throwable $exception) {
                    $validation['pwd'] = "Password mismatch.";
                }
            } else {
                foreach ($note->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            }
        } else if (!$enc) {
            $txt = $note->txtDecrypted;
        }

        if (!$enc) {
            $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
            $rl = $note->readlimit;
            if ($rl > 0) {
                $rl--;
                if ($rl === 0) {
                    $note->delete();
                } else {
                    $note->reset();
                    $note->update(['readlimit' => $rl], ['readlimit']);
                    syslog(LOG_DEBUG, print_r($note->getMessages(), true));
                }
            }

            return $this->response->setHeader('Content-Type', 'text/plain')->setContent($txt);
        }

        return $this->view->setVars([
            'validation' => $validation,
            'action' => $action,
            'tokenKey' => $this->security->getTokenKey(),
            'tokenValue' => $this->security->getToken(),
            'maxPwdLength' => $this->config->path('app.max_pwd_length', 8),
            'minPwdLength' => $this->config->path('app.min_pwd_length', 4),
        ]);
    }

    /**
     * @param Exception $exception
     */
    public function errorAction(Exception $exception)
    {
        $this->log->error(
            $exception->getMessage() .
            "\n" .
            $exception->getTraceAsString()
        );
        $status = 500;
        $text = UtilHttpException::TXT_INTERNAL_SERVER;
        if ($exception instanceof UtilHttpException) {
            $status = $exception->getCode();
            $text = $exception->getMessage();
        }
        if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {
            switch ($exception->getCode()) {
                case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                    $status = 404;
                    $text = UtilHttpException::TXT_NOT_FOUND;
            }
        }
        $this->response->setStatusCode($status);
        $this->view->setVar('error', $text);
    }
}