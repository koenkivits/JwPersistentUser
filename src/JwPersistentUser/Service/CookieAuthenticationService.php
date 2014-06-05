<?php

namespace JwPersistentUser\Service;

use JwPersistentUser\Authentication\Adapter\ForceLogin;

use Zend\Http\Request,
    Zend\Http\Response,
    Zend\Stdlib\RequestInterface,
    Zend\Stdlib\ResponseInterface,
    Zend\Authentication\AuthenticationService,
    Zend\ServiceManager\ServiceLocatorAwareTrait;

/**
 * Note that dependencies in this service are pulled directory out of the service locator for
 * a reason: performance. Would we NOT do this than we would instantiate those resources on
 * every request.
 */
class CookieAuthenticationService
{
    use ServiceLocatorAwareTrait;

    /**
     * @var AuthenticationService
     */
    protected $authService;

    /**
     * @var RememberMeService
     */
    protected $rememberMeService;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function loginFrom(RequestInterface $request, ResponseInterface $response)
    {
        if (!($request instanceof Request) || !($response instanceof Response)) {
            return;
        }

        $serieToken = CookieMonster::read($request, $response);
        if (!$serieToken) {
            return;
        }

        // TODO use a session once we validated using Cookie. So we do not need to instantiate
        //      the AuthService on every following request.

        if ($this->getAuthService()->hasIdentity()) {
            return;
        }

        $nextSerieToken = $this->getRememberMeService()->getNextInSerie($serieToken);
        if (!$nextSerieToken) {
            $this->invalidAttempt($response);
            return;
        }

        CookieMonster::writeSerie($response, $nextSerieToken);

        $this->getAuthService()->authenticate(new ForceLogin($nextSerieToken->getUserId()));
    }

    /**
     * @param Response $response
     */
    protected function invalidAttempt(Response $response)
    {
        $this->getAuthService()->clearIdentity();

        CookieMonster::writeNull($response);
    }

    /**
     * @return AuthenticationService
     */
    public function getAuthService()
    {
        if ($this->authService === null) {
            $this->authService = $this->getServiceLocator()->get('zfcuser_auth_service');
        }

        return $this->authService;
    }

    /**
     * @param AuthenticationService $authService
     * @return $this
     */
    public function setAuthService($authService)
    {
        $this->authService = $authService;
        return $this;
    }

    /**
     * @return RememberMeService
     */
    public function getRememberMeService()
    {
        if ($this->rememberMeService === null) {
            $this->rememberMeService = $this->getServiceLocator()->get('JwPersistentUser\Service\RememberMe');
        }

        return $this->rememberMeService;
    }

    /**
     * @param RememberMeService $rememberMeService
     * @return $this
     */
    public function setRememberMeService($rememberMeService)
    {
        $this->rememberMeService = $rememberMeService;
        return $this;
    }
}