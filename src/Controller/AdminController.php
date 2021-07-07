<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Skill;
use App\Entity\Training;
use App\Entity\Occupation;
use App\Form\Type\UserPasswordType;
use App\Form\Type\UserType;
use App\Repository\NotificationRepository;
use App\Repository\SkillRepository;
use App\Repository\OccupationRepository;
use App\Repository\TrainingRepository;
use App\Repository\TrainingSkillRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @Route("/admin", name="admin_")
 */
class AdminController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index(
        Request $request,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        UserPasswordEncoderInterface $passwordEncoder,
        SkillRepository $skillRepository,
        UserRepository $userRepository,
        OccupationRepository $occupationRepository,
        NotificationRepository $notificationRepository,
        TrainingRepository $trainingRepository
    ): Response
    {
        if (!$this->isGranted(User::ROLE_ADMIN))
            return $this->redirectToRoute('app_home');

        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user, ['is_personal' => true]);
        $passwordForm = $this->createForm(UserPasswordType::class, $user);

        $form->handleRequest($request);
        $passwordForm->handleRequest($request);
        $tab = (array_key_exists('tab_admin_silkc', $_COOKIE)) ? $_COOKIE['tab_admin_silkc'] : 1;
        setcookie('tab_admin_silkc', "", time() - 3600, "/");
        
        if ($form->isSubmitted() && $form->isValid()) {
            $tab = 3;

            $em = $this->getDoctrine()->getManager();

            $errors = $validator->validate($user);
            if (count($errors) > 0)
                return new Response((string)$errors, 400);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $translator->trans('Updated data', [], 'admin'));

            //return $this->redirectToRoute('admin_home');
        } else if ($passwordForm->isSubmitted()) {
            if (!$passwordForm->isValid()) {
                $errors = $validator->validate($user);
                $errorsString = (string) $errors;
                return new Response($errorsString);
            }

            $tab = 7;

            //$data = $request->request->all('user_password');
            //$result = $passwordEncoder->isPasswordValid($user, 'test');
            $user->setPassword($passwordEncoder->encodePassword($user, $user->getPassword()));

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $translator->trans('Updated data', [], 'admin'));
        }

        return $this->render(
            'admin/index.html.twig',
            [
                'user' => $user,
                'form' => $form->createView(),
                'notifications' => $notificationRepository->findBy(['isRead' => false]),
                'to_validated_trainings' => $trainingRepository->findBy(['isValidated' => false]),
                'trainings' => $trainingRepository->findAll(),
                'skills' => $skillRepository->findAll(),
                'occupations' => $occupationRepository->findAll(),
                'password_form' => $passwordForm->createView(),
                //'users' => $userRepository->findByRole('ROLE_USER'),
                'users' => $userRepository->findAll(),
                'tab' => $tab
                //'related_skills' => $skillRepository->getByOccupationAndTraining($user)
            ]
        );
    }

    /**
     * @Route("/edit_user/{id}", name="edit_user", methods={"GET", "POST"})
     */
    public function edit_user(User $user, Request $request, ValidatorInterface $validator, TranslatorInterface $translator)
    {
        $form = $this->createForm(UserType::class, $user, ['is_personal' => true, 'by_admin' => true]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();

            $errors = $validator->validate($user);
            if (count($errors) > 0)
                return new Response((string) $errors, 400);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $translator->trans('Updated data', [], 'admin'));
        }

        setcookie('tab_admin_silkc', 8, time() + 86400, "/");

        return $this->render(
            'admin/edit_user.html.twig',
            [
                'user' => $user,
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/read", name="read")
     */
    public function read(
        Request $request,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        UserPasswordEncoderInterface $passwordEncoder,
        SkillRepository $skillRepository,
        UserRepository $userRepository,
        NotificationRepository $notificationRepository,
        TrainingRepository $trainingRepository
    ): Response
    {
        
    }

    /**
     * @Route("/get_skill_related_trainings/{id}", name="get_skill_related_trainings")
     */
    public function get_skill_related_trainings(
        Skill $skill,
        Request $request,
        TrainingSkillRepository $trainingSkillRepository,
        TrainingRepository $trainingRepository
    )
    {
        $trainingSkills = $trainingSkillRepository->findBy(['skill' => $skill, 'isToAcquire' => true]);

        $trainings = new ArrayCollection();
        if ($trainingSkills) {
            foreach ($trainingSkills as $trainingSkill) {
                $trainings->add($trainingSkill->getTraining());
            }
        }

        return $this->json(
            [
                'result' => true,
                'skill' => $skill,
                'trainings' => $trainings
            ],
            200,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * @Route("/get_occupation_related_trainings/{id}", name="get_occupation_related_trainings")
     */
    public function get_occupation_related_trainings(
        Occupation $occupation,
        Request $request,
        TrainingRepository $trainingRepository
    )
    {
        $trainings = $trainingRepository->findBy(['occupation' => $occupation]);

        return $this->json(
            [
                'result' => true,
                'occupation' => $occupation,
                'trainings' => $trainings
            ],
            200,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * @Route("/suspend_user/{id}", name="suspend_user", methods="POST")
     */
    public function suspend_user(User $user)
    {
        $user->setIsSuspended(true);
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $this->json(
            ['result' => true],
            200,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * @Route("/unsuspend_user/{id}", name="unsuspend_user", methods="POST")
     */
    public function unsuspend_user(User $user)
    {
        $user->setIsSuspended(false);
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $this->json(
            ['result' => true],
            200,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * @Route("/suspect_user/{id}", name="suspect_user", methods="POST")
     */
    public function suspect_user(User $user)
    {
        $user->setIsSuspected(true);
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $this->json(
            ['result' => true],
            200,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * @Route("/raise_suspicion/{id}", name="raise_suspicion", methods="POST")
     */
    public function raise_suspicion(User $user)
    {
        $user->setIsSuspected(false);
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $this->json(
            ['result' => true],
            200,
            ['Access-Control-Allow-Origin' => '*']
        );
    }
}