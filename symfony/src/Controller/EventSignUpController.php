<?php
namespace App\Controller;

use App\Controller\EventController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Security\Core\Security;
use App\Helper\Mattermost;
use App\Entity\Event;
use App\Entity\Artist;
use App\Entity\RSVP;
use App\Entity\EventArtistInfo;
use App\Entity\NakkiBooking;
use App\Form\EventArtistInfoType;
use App\Form\ArtistType;

/**
 * @IsGranted("ROLE_USER")
 */
class EventSignUpController extends EventController
{
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     */
    public function nakkiCancel(
        Request $request,
        Event $event, 
        Mattermost $mm, 
        NakkiBooking $booking 
    ): Response
    {
        $member = $this->getUser()->getMember();
        if ($booking->getMember() == $member){
            $booking->setMember(null);
            $em = $this->getDoctrine()->getManager();
            $em->persist($booking);
            $em->flush();
            $text = $text = '**Nakki reservation cancelled from event '.$booking.'**';
            $mm->SendToMattermost($text, 'nakkikone');
            $this->addFlash('success', 'Nakki cancelled!');
        }
        return $this->redirect($request->headers->get('referer'));
        //return $this->redirectToRoute('entropy_event_slug_nakkikone', ['slug' => $slug, 'year' => $year]);
    }
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     */
    public function nakkiSignUp(
        Request $request,
        Event $event, 
        Mattermost $mm, 
        NakkiBooking $booking 
    ): Response
    {
        $member = $this->getUser()->getMember();
        $em = $this->getDoctrine()->getManager();
        if ($event->getNakkikoneEnabled()){
            $repo = $em->getRepository('App:NakkiBooking');
            $sameTime = $repo->findMemberEventBookingsAtSameTime($member, $event, $booking->getStartAt(), $booking->getEndAt());
            if($sameTime){
                $this->addFlash('danger', 'You cannot reserve overlapping Nakkis!');
                return $this->redirect($request->headers->get('referer'));
            }
            $booking->setMember($member);
            $em->persist($booking);
            $em->flush();
            $text = $text = '**New Nakki reservation: '.$booking.'**';
            $mm->SendToMattermost($text, 'nakkikone');
            $this->addFlash('success', 'Nakki reserved!');
        } else {
            $this->addFlash('warning', 'Nakkikone is not enabled!');
        }
        return $this->redirect($request->headers->get('referer'));
    }
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     */
    public function nakkikone(
        Request $request,
        Event $event 
    ): Response
    {
        $locale = $request->getLocale();
        $member = $this->getUser()->getMember();
        $repo = $this->getDoctrine()->getManager()->getRepository('App:NakkiBooking');
        $selected = $repo->findMemberEventBookings($member, $event);
        $nakkis = [];
        foreach ( $event->getNakkiBookings() as $booking ){
            $name = $booking->getNakki()->getDefinition()->getName($locale);
            $duration = $booking->getStartAt()->diff($booking->getEndAt())->format('%h');
            $nakkis[$name]['description'] = $booking->getNakki()->getDefinition()->getDescription($locale);
            $nakkis[$name]['bookings'][] = $booking;
            $nakkis[$name]['durations'][$duration] = $duration;
        }
        if (!$event->getNakkikoneEnabled()){
            $this->addFlash('warning', 'Nakkikone is not enabled!');
        }
        return $this->render('nakkikone.html.twig', [
            'selected' => $selected,
            'event' => $event,
            'nakkis' => $nakkis
            //'form' => $form->createView(),
        ]);
    }
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     */
    public function RSVP(
        Request $request, 
        Event $event, 
        TranslatorInterface $trans
    ): Response
    {
        $member = $this->getUser()->getMember();
        if(empty($member)){
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
        foreach ($member->getRSVPs() as $rsvp){
            if ($rsvp->getEvent() == $event){
                $this->addFlash('warning', $trans->trans('rsvp.already_rsvpd'));
                return $this->redirectToRoute('entropy_event_slug', ['slug' => $slug, 'year' => $year]);
            } 
        }
        $this->em = $this->getDoctrine()->getManager();
        $rsvp = new RSVP();
        $rsvp->setEvent($event);
        $rsvp->setMember($member);
        $this->em->persist($rsvp);
        $this->em->flush();
        $this->addFlash('success', $trans->trans('rsvp.rsvpd_succesfully'));
        return $this->redirectToRoute('entropy_event_slug', ['slug' => $slug, 'year' => $year]);

    }
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     */
    public function artistSignUp(
        Request $request, 
        Event $event, 
        TranslatorInterface $trans
    ): Response
    {
        $artists = $this->getUser()->getMember()->getArtist();
        if (count($artists)==0){
            $this->addFlash('warning', $trans->trans('no_artsit_create_one'));
            $request->getSession()->set('referer', $request->getPathInfo());
            return new RedirectResponse($this->generateUrl('entropy_artist_create'));
        }
        // TODO: remove signed up user artists
        //foreach ($artists as $artist){
        //    if( $artist->getArtistInfos()->contains($event)
        /*if (count($form_artists)==0){
            $this->addFlash('warning', $trans->trans('all_artists_signed_up_create_one'));
            $request->getSession()->set('referer', $request->getPathInfo());
            return new RedirectResponse($this->generateUrl('entropy_artist_create'));
        }*/
        $this->em = $this->getDoctrine()->getManager();
        $artisteventinfo = new EventArtistInfo();
        $artisteventinfo->setEvent($event);
        $form = $this->createForm(EventArtistInfoType::class, $artisteventinfo, ['artists' => $artists]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $info = $form->getData();
            $artist = $info->getArtist();
            $artistClone = new Artist();
            $artistClone->setCopyForArchive(true);
            $artistClone->setName($artist->getName().' for '.$event->getName());
            $artistClone->setPicture($artist->getPicture());
            $artistClone->setBio($artist->getBio());
            $artistClone->setBioEn($artist->getBioEn());
            $artistClone->setLinks($artist->getLinks());
            $artistClone->setGenre($artist->getGenre());
            $artistClone->setType($artist->getType());
            $info->setArtistClone($artistClone);
            $this->em->persist($artistClone);
            $this->em->persist($info);
            try {
                $this->em->flush();
                $this->addFlash('success', $trans->trans('succesfully_signed_up_for_the_party'));
                return new RedirectResponse($this->generateUrl('entropy_profile'));
            } catch (\Exception $e) {
                $this->addFlash('warning', $trans->trans('this_artist_signed_up_already'));

            }
        }
        //$page = $cms->retrieve()->getCurrentPage();
        return $this->render('artist/signup.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }
}