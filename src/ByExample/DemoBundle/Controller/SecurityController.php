<?php

/**
 * This file is part of the FOSRestByExample package.
 *
 * (c) Santiago Diaz <santiago.diaz@me.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ByExample\DemoBundle\Controller;

use ByExample\DemoBundle\Entity\User;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations\QueryParam,
    FOS\RestBundle\Controller\Annotations\RequestParam,
    FOS\RestBundle\Controller\Annotations\Prefix,
    FOS\RestBundle\Controller\Annotations\NamePrefix,
    FOS\RestBundle\Controller\Annotations\View,
    FOS\RestBundle\Request\ParamFetcher,
    FOS\RestBundle\View\RouteRedirectView,
    FOS\RestBundle\View\View AS FOSView;
use Symfony\Bundle\FrameworkBundle\Controller\Controller,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Security\Core\Exception\AccessDeniedException,
    Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken,
    Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * Controller that provides Restfuls security functions.
 *
 * @Prefix("/login_api")
 * @NamePrefix("byexample_demo_securityrest_")
 * @author  Santiago Diaz <santiago.diaz@me.com>
 * @version Release: 0.1
 */
class SecurityController extends Controller
{

    /**
     * WSSE authentication
     *
     * @return FOSView
     * @throws AccessDeniedException
     * @ApiDoc()
     */
    public function postLoginAction()
    {

        $view = FOSView::create();
        $request = $this->getRequest();

        $username = $request->get('_username');
        $password = $request->get('_password');

        //$csrfToken = $this->container->get('form.csrf_provider')->generateCsrfToken('authenticate');
        //$data = array('csrf_token' => $csrfToken,);

        $um = $this->get('fos_user.user_manager');
        $user = $um->findUserByUsernameOrEmail($username);

        if (!$user instanceof User) {
            throw new AccessDeniedException("Wrong user");
        }
        if (!$this->checkUserPassword($user, $password)) {
            throw new AccessDeniedException("Wrong password");
        }

        $created = date('c');
        $nonce = substr(md5(uniqid('nonce_', true)), 0, 16);
        $nonce64 = base64_encode($nonce);
        $passwordDigest = base64_encode(sha1($nonce . $created . $user->getPassword(), true));
        $header = "UsernameToken Username=\"{$username}\", PasswordDigest=\"{$passwordDigest}\", Nonce=\"{$nonce64}\", Created=\"{$created}\"";
        $this->loginUser($user);

        //AGREGAR HEADERS PARA WSSE
        $view->setHeader("Authorization", 'WSSE profile="UsernameToken"');
        $view->setHeader("X-WSSE", "UsernameToken Username=\"{$username}\", PasswordDigest=\"{$passwordDigest}\", Nonce=\"{$nonce64}\", Created=\"{$created}\"");

        $data = array('WSSE' => $header);
        $view->setStatusCode(200)->setData($data);
        return $view;
    }

  /**
     * Devuelve un estado logout
     *
     * @return FOSView
     * @ApiDoc()
     */
    public function getLogoutAction()
    {
        $view = FOSView::create();
        $security = $this->get('security.context');
        $token = new AnonymousToken(null, new User());
        $security->setToken($token);
        $this->get('session')->invalidate();
        $view->setStatusCode(200)->setData('Logout con éxito');
        return $view;
    }

    /**
     * Login user
     *
     * @param User $user user
     *
     * @return void
     */
    protected function loginUser(User $user)
    {
        $security = $this->get('security.context');
        $providerKey = $this->container->getParameter('fos_user.firewall_name');
        $roles = $user->getRoles();
        $token = new UsernamePasswordToken($user, null, $providerKey, $roles);
        $security->setToken($token);
    }

    /**
     * Check user Password
     *
     * @param User   $user     user
     * @param string $password password
     *
     * @return boolean
     */
    protected function checkUserPassword(User $user, $password)
    {
        $factory = $this->get('security.encoder_factory');
        $encoder = $factory->getEncoder($user);
        return $encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt());
    }


}