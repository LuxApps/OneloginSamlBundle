<?php

namespace Hslavich\OneloginSamlBundle\Controller;

use Hslavich\OneloginSamlBundle\Security\Firewall\SamlListener;
use Hslavich\OneloginSamlBundle\Security\Utils\OneLoginAuthRegistry;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Request;

class SamlController extends AbstractController
{
    /**
     * @var OneLoginAuthRegistry
     */
    private $authRegistry;

    public function __construct(OneLoginAuthRegistry $authRegistry)
    {
        $this->authRegistry = $authRegistry;
    }

    public function loginAction(Request $request, $idp = null)
    {
        $authErrorKey = Security::AUTHENTICATION_ERROR;
        $session = $targetPath = null;

        if ($request->hasSession()) {
            $session = $request->getSession();
            $targetPath = $session->get('_security.main.target_path');
        }

        if ($request->attributes->has($authErrorKey)) {
            $error = $request->attributes->get($authErrorKey);
        } elseif (null !== $session && $session->has($authErrorKey)) {
            $error = $session->get($authErrorKey);
            $session->remove($authErrorKey);
        } else {
            $error = null;
        }

        if ($error) {
            throw new \RuntimeException($error->getMessage());
        }

        if (null !== $idp) {
            $session->set(SamlListener::IDP_NAME_SESSION_NAME, $idp);
        }

        $this->authRegistry->getIdpAuth($idp)->login();
    }

    public function metadataAction($idp = null)
    {
        try {
            $auth = $this->authRegistry->getIdpAuth($idp);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(sprintf('IDP %s not found', $idp), $e);
        }

        $metadata = $auth->getSettings()->getSPMetadata();

        $response = new Response($metadata);
        $response->headers->set('Content-Type', 'xml');

        return $response;
    }

    public function assertionConsumerServiceAction()
    {
        throw new \RuntimeException('You must configure the check path to be handled by the firewall.');
    }

    public function singleLogoutServiceAction()
    {
        throw new \RuntimeException('You must activate the logout in your security firewall configuration.');
    }
}
