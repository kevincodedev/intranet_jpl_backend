<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * @Route("/reset-password")
 */
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    private $resetPasswordHelper;
    private $entityManager;
    private $frontendUrl;

    public function __construct(
        ResetPasswordHelperInterface $resetPasswordHelper,
        EntityManagerInterface $entityManager,
        string $frontendUrl
    ) {
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->entityManager = $entityManager;
        $this->frontendUrl = $frontendUrl;
    }

    /**
     * Processes the form to request a password change
     *
     * @Route("", name="app_forgot_password_request")
     */
    public function request(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);

        // JSON request support
        if ($request->getContentType() === 'json' || strpos($request->headers->get('Content-Type'), 'application/json') !== false) {
            $data = json_decode($request->getContent(), true);
            $form->submit($data);
        } else {
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $emailData = $form->get('email')->getData();
            
            try {
                $this->processSendingPasswordResetEmail($emailData, $mailer);
            } catch (ResetPasswordExceptionInterface $e) {
                // If it's a JSON request and we hit throttling or other issue
                if ($request->getContentType() === 'json' || $request->isXmlHttpRequest()) {
                    return $this->json([
                        'error' => 'No se pudo enviar el correo: ' . $e->getReason()
                    ], Response::HTTP_TOO_MANY_REQUESTS);
                }

                return $this->redirectToRoute('app_check_email');
            }

            // JSON answer for the API calls
            if ($request->getContentType() === 'json' || $request->isXmlHttpRequest()) {
                return $this->json([
                    'message' => 'Si existe una cuenta asociada a ese correo, se ha enviado un enlace para restablecer la contraseña.'
                ]);
            }

            return $this->redirectToRoute('app_check_email');
        }

        //Returns JSON error if the request isn´t valid
        if (($request->getContentType() === 'json' || $request->isXmlHttpRequest()) && $form->isSubmitted()) {
            return $this->json(['errors' => (string) $form->getErrors(true, false)], Response::HTTP_BAD_REQUEST);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    /**
     * PConfirms that the user has requested a password change
     *
     * @Route("/check-email", name="app_check_email")
     */
    public function checkEmail(): Response
    {
        // Always generates a request token even if email is invalid
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validades the clicked email link
     *
     * @Route("/reset/{token}", name="app_reset_password")
     */
    public function reset(Request $request, UserPasswordEncoderInterface $userPasswordEncoder, string $token = null): Response
    {
        $isJson = $request->getContentType() === 'json' || strpos($request->headers->get('Content-Type'), 'application/json') !== false;

        if ($token) {
            // We store the token in session and remove it from the URL, to prevent the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            // BUT for JSON API requests, we skip this to remain stateless.
            if (!$isJson) {
                $this->storeTokenInSession($token);
                return $this->redirectToRoute('app_reset_password');
            }
        } else {
            $token = $this->getTokenFromSession();
        }

        if (null === $token) {
            if ($isJson) {
                return $this->json(['error' => 'No se encontró el token de restablecimiento.'], Response::HTTP_BAD_REQUEST);
            }
            throw $this->createNotFoundException('No se encontró el token de restablecimiento en la URL o en la sesión.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            if ($isJson) {
                return $this->json(['error' => 'Token inválido o expirado: ' . $e->getReason()], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE,
                $e->getReason()
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // Allows the user to change their password
        $form = $this->createForm(ChangePasswordFormType::class);

        // Support for JSON requests
        if ($request->getContentType() === 'json' || strpos($request->headers->get('Content-Type'), 'application/json') !== false) {
            $data = json_decode($request->getContent(), true);
            $form->submit($data);
        } else {
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Email token is one-use only
            $this->resetPasswordHelper->removeResetRequest($token);

            // The password is encrypted and saved
            $encodedPassword = $userPasswordEncoder->encodePassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();

            // When the password is saved, clean the session
            $this->cleanSessionAfterReset();

            // Returns the answer if successful
            if ($request->getContentType() === 'json' || $request->isXmlHttpRequest()) {
                return $this->json(['message' => 'Contraseña actualizada correctamente.']);
            }

            // Redirect to frontend login
            return $this->redirect($this->frontendUrl . '/login');
        }

        //Error JSON response
        if ($isJson && $form->isSubmitted()) {
            return $this->json(['errors' => (string) $form->getErrors(true, false)], Response::HTTP_BAD_REQUEST);
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    //Sends the email to the user
    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return;
        }

        $resetToken = $this->resetPasswordHelper->generateResetToken($user);

        $email = (new TemplatedEmail())
            ->from(new Address('lmacarapaica@pafar.net', 'Testing Email'))
            ->to($user->getEmail())
            ->subject('Solicitud de restablecimiento de contraseña')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'frontendUrl' => $this->frontendUrl,
            ]);

        $mailer->send($email);

        // Saves request to database
        $this->entityManager->flush();
    }
}
