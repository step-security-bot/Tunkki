<?php

namespace App\Controller;

use App\Controller\EventController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Helper\Mattermost;
use App\Repository\NakkiBookingRepository;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\Artist;
use App\Entity\RSVP;
use App\Entity\EventArtistInfo;
use App\Entity\NakkiBooking;
use App\Form\EventArtistInfoType;
use App\Form\EventArtistInfoEditType;
use Doctrine\ORM\EntityManagerInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventSignUpController extends EventController
{
    public function nakkiCancel(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        Mattermost $mm,
        NakkiBooking $booking,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        if ($booking->getMember() == $member) {
            $booking->setMember(null);
            $em->persist($booking);
            $em->flush();
            $text = $text = '**Nakki reservation cancelled from event ' . $booking . '**';
            $mm->SendToMattermost($text, 'nakkikone');
            $this->addFlash('success', 'Nakki cancelled');
        }
        return $this->redirect($request->headers->get('referer'));
        //return $this->redirectToRoute('entropy_event_slug_nakkikone', ['slug' => $slug, 'year' => $year]);
    }
    public function nakkiSignUp(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        Mattermost $mm,
        NakkiBooking $booking,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        if (!$member->getUsername()) {
            $this->addFlash('danger', 'Nakki is not reserved! Please define username in you profile');
            return $this->redirect($request->headers->get('referer'));
        }
        if ($event->getNakkikoneEnabled()) {
            if (is_null($booking->getMember())) {
                $repo = $em->getRepository(NakkiBooking::class);
                if ($event->getRequireNakkiBookingsToBeDifferentTimes()) {
                    $sameTime = $repo->findMemberEventBookingsAtSameTime($member, $event, $booking->getStartAt(), $booking->getEndAt());
                    if ($sameTime) {
                        $this->addFlash('danger', 'You cannot reserve overlapping Nakkis');
                        return $this->redirect($request->headers->get('referer'));
                    }
                }
                $booking->setMember($member);
                $em->persist($booking);
                $em->flush();
                $count = $repo->findEventNakkiCount($booking, $event);
                $text = $text = '**Nakki reservation** ' . $booking . ' (' . $count . ')';
                $mm->SendToMattermost($text, 'nakkikone');
                $this->addFlash('success', 'Nakki reserved');
            } else {
                $this->addFlash('warning', 'Sorry but someone reserved that one already');
            }
        } else {
            $this->addFlash('warning', 'Nakkikone is not enabled');
        }
        return $this->redirect($request->headers->get('referer'));
    }
    public function nakkikone(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        NakkiBookingRepository $repo
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $selected = $repo->findMemberEventBookings($member, $event);
        if (!$event->getNakkikoneEnabled()) {
            $this->addFlash('warning', 'Nakkikone is not enabled');
        }
        return $this->render('nakkikone.html.twig', [
            'selected' => $selected,
            'event' => $event,
            'nakkis' => $this->getNakkis($event, $member, $request->getLocale())
            //'form' => $form->createView(),
        ]);
    }
    public function responsible(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
    ): Response {
        return $this->render('list_nakki_info_for_responsible.html.twig', [
            'event' => $event,
            //'form' => $form->createView(),
        ]);
    }
    protected function getNakkis($event, $member, $locale): array
    {
        $nakkis = [];
        foreach ($event->getNakkiBookings() as $booking) {
            $name = $booking->getNakki()->getDefinition()->getName($locale);
            $duration = $booking->getStartAt()->diff($booking->getEndAt())->format('%h');
            if ($booking->getNakki()->getDefinition()->getOnlyForActiveMembers()) {
                if ($member->getIsActiveMember()) {
                    $nakkis[$name]['description'] = $booking->getNakki()->getDefinition()->getDescription($locale);
                    $nakkis[$name]['bookings'][] = $booking;
                    $nakkis[$name]['durations'][$duration] = $duration;
                    if (is_null($booking->getMember())) {
                        if (!array_key_exists('not_reserved', $nakkis[$name])) {
                            $nakkis[$name]['not_reserved'] = 1;
                        } else {
                            $nakkis[$name]['not_reserved'] += 1;
                        }
                    }
                }
            } else {
                $nakkis[$name]['description'] = $booking->getNakki()->getDefinition()->getDescription($locale);
                $nakkis[$name]['bookings'][] = $booking;
                $nakkis[$name]['durations'][$duration] = $duration;
                if (is_null($booking->getMember())) {
                    if (!array_key_exists('not_reserved', $nakkis[$name])) {
                        $nakkis[$name]['not_reserved'] = 1;
                    } else {
                        $nakkis[$name]['not_reserved'] += 1;
                    }
                }
            }
        }
        return $nakkis;
    }
    protected function getNakkiFromGroup($event, $member, $selected, $locale)
    {
        $nakkis = [];
        foreach ($event->getNakkis() as $nakki) {
            foreach ($selected as $booking) {
                if ($booking->getNakki() == $nakki) {
                    $nakkis = $this->addNakkiToArray($nakkis, $booking, $locale);
                    break;
                }
            }
            if (!array_key_exists($nakki->getDefinition()->getName($locale), $nakkis)) {
                // try to prevent displaying same nakki to 2 different users using the system at the same time
                $bookings = $nakki->getNakkiBookings()->toArray();
                shuffle($bookings);
                foreach ($bookings as $booking) {
                    if (is_null($booking->getMember())) {
                        $nakkis = $this->addNakkiToArray($nakkis, $booking, $locale);
                        break;
                    }
                }
            }
        }
        return $nakkis;
    }
    protected function addNakkiToArray($nakkis, $booking, $locale): array
    {
        $name = $booking->getNakki()->getDefinition()->getName($locale);
        $duration = $booking->getStartAt()->diff($booking->getEndAt())->format('%h');
        $nakkis[$name]['description'] = $booking->getNakki()->getDefinition()->getDescription($locale);
        $nakkis[$name]['bookings'][] = $booking;
        $nakkis[$name]['durations'][$duration] = $duration;
        return $nakkis;
    }
    public function RSVP(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        TranslatorInterface $trans,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();

        if (empty($member)) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }

        $slug = $event->getUrl();
        $year = $request->get('year');
        /*
        if(empty($slug)){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $event = $this->em->getRepository(Event::class)
                          ->findEventBySlugAndYear($slug, $year);*/
        foreach ($member->getRSVPs() as $rsvp) {
            if ($rsvp->getEvent() == $event) {
                $this->addFlash('warning', $trans->trans('rsvp.already_rsvpd'));
                return $this->redirectToRoute('entropy_event_slug', ['slug' => $slug, 'year' => $year]);
            }
        }
        $rsvp = new RSVP();
        $rsvp->setEvent($event);
        $rsvp->setMember($member);
        $em->persist($rsvp);
        $em->flush();
        $this->addFlash('success', $trans->trans('rsvp.rsvpd_succesfully'));
        return $this->redirectToRoute('entropy_event_slug', ['slug' => $slug, 'year' => $year]);
    }
    public function artistSignUp(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        TranslatorInterface $trans,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $artists = $member->getArtist();
        if ((is_countable($artists) ? count($artists) : 0) == 0) {
            $this->addFlash('warning', $trans->trans('no_artsit_create_one'));
            $request->getSession()->set('referer', $request->getPathInfo());
            return new RedirectResponse($this->generateUrl('entropy_artist_create'));
        }
        if (!$event->getArtistSignUpNow()) {
            $this->addFlash('warning', $trans->trans('Not allowed'));
            return new RedirectResponse($this->generateUrl('entropy_profile'));
        }
        $artisteventinfo = new EventArtistInfo();
        $artisteventinfo->setEvent($event);
        $form = $this->createForm(EventArtistInfoType::class, $artisteventinfo, ['artists' => $artists]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $info = $form->getData();
            $artist = $info->getArtist();
            $artistClone = new Artist();
            $artistClone->setCopyForArchive(true);
            $artistClone->setName($artist->getName() . ' for ' . $event->getName());
            $artistClone->setPicture($artist->getPicture());
            $artistClone->setHardware($artist->getHardware());
            $artistClone->setBio($artist->getBio());
            $artistClone->setBioEn($artist->getBioEn());
            $artistClone->setLinks($artist->getLinks());
            $artistClone->setGenre($artist->getGenre());
            $artistClone->setType($artist->getType());
            $info->setArtistClone($artistClone);
            $em->persist($artistClone);
            $em->persist($info);
            try {
                $em->flush();
                $this->addFlash('success', $trans->trans('succesfully_signed_up_for_the_party'));
                return new RedirectResponse($this->generateUrl('entropy_profile'));
            } catch (\Exception) {
                $this->addFlash('warning', $trans->trans('this_artist_signed_up_already'));
            }
        }
        //$page = $cms->retrieve()->getCurrentPage();
        return $this->render('artist/signup.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }
    #[Route(
        '/{year}/{slug}/signup/{id}/edit',
        name: 'entropy_event_slug_artist_signup_edit',
        requirements: [
            'slug' => '\w+',
            'year' => '\d+',
            'id' => '\d+',
        ]
    )]
    public function artistSignUpEdit(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        EventArtistInfo $artisteventinfo,
        TranslatorInterface $trans,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        if ($artisteventinfo->getArtist()->getMember() != $member) {
            $this->addFlash('warning', $trans->trans('Not allowed!'));
            return new RedirectResponse($this->generateUrl('entropy_artist_profile'));
        }
        $form = $this->createForm(EventArtistInfoEditType::class, $artisteventinfo);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $info = $form->getData();
            $em->persist($info);
            try {
                $em->flush();
                $this->addFlash('success', $trans->trans('event.form.sign_up.request_edited'));
                return new RedirectResponse($this->generateUrl('entropy_artist_profile'));
            } catch (\Exception) {
                $this->addFlash('warning', $trans->trans('Something went wrong!'));
            }
        }
        //$page = $cms->retrieve()->getCurrentPage();
        return $this->render('artist/signup.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }
}
