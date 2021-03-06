<?php


namespace App\Controller;

use App\Entity\Invite;
use App\Form\UserType;
use App\Services\FileUploader;
use DateTime;
use App\Services\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Services\UserService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Security("is_granted('ROLE_USER')")
 */
class UserController extends AbstractController
{

    /**
     * @Route("/flatmate/{uuid}", name="profile.view", methods={"GET"})
     */
    public function showUserProfile(
        UserService $userService,
        $uuid,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): Response {
        $user = $userService->getUserByUUID($uuid);
        $userAge = $userService->getUserAge($user);
        $now = new DateTime();
        $intervalFromLastVisit = $now->diff($user->getLastActivityAt());

        if ($intervalFromLastVisit->d > 0) {
            $lastVisit = $intervalFromLastVisit->d . 'd';
        } elseif ($intervalFromLastVisit->h > 0) {
            $lastVisit = $intervalFromLastVisit->h . 'h';
        } else {
            $lastVisit = $intervalFromLastVisit->i . 'min';
        }

        $invitesRepo = $em->getRepository(Invite::class);
        $invite = $invitesRepo->findUserToUserInvite(
            $this->getUser()->getId(),
            $uuid
        );

        $notificationService->removeInviteNotificationsByUser(
            $this->getUser(),
            $uuid
        );

        return isset($user)
            ? $this->render('profile/profileView.html.twig', [
                'user' => $user,
                'userAge' => $userAge,
                'match' => $invite,
                'lastVisit' => $lastVisit
            ])
            : $this->render('profile/profileNotFound.html.twig');
    }

    /**
     * @Route("/dashboard/profile", name="profile.edit",)
     */
    public function editUserProfile(
        Request $request,
        UserService $userService,
        FileUploader $fileUploader
    ) {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $userAge = $userService->getUserAge($user);
        $userId = $user->getId()->toString();
        $userProfilePicture = $user->getProfilePicture();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->getData()->getProfilePicture();
            $gender = $form->getData()->getGender();

            if (isset($file)) {
                $fileName = $fileUploader->uploadProfilePicture($file, $userId);
                $user->setProfilePicture($fileName);
            } elseif (!preg_match('/build\/.*/', $userProfilePicture)) {
                $user->setProfilePicture($userProfilePicture);
            } else {
                $user->setProfilePicture('build/' . $gender . '.png');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->merge($user);
            $entityManager->flush();

            return $this->redirectToRoute('matched');
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->refresh($user);
        }

        return $this->render('profile/profileEdit.html.twig', [
            'form' => $form->createView(),
            'userAge' => $userAge,
        ]);
    }

    /**
     * @Route("/user/activate", name="user_activate", methods={"POST"})
     */
    public function activateUser(EntityManagerInterface $em, Request $request)
    {
        $this->getUser()->setIsActive(true);
        $em->flush();
        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * @Route("/user/inactivate", name="user_inactivate", methods={"POST"})
     */
    public function inactivateUser(EntityManagerInterface $em, Request $request)
    {
        $this->getUser()->setIsActive(false);
        $em->flush();
        return $this->redirect($request->headers->get('referer'));
    }
}
