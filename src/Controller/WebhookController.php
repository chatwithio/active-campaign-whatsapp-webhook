<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Services\Dialog360Service;
use Psr\Log\LoggerInterface;

#[Route('/webhook', name: 'app_webhook')]
class WebhookController extends AbstractController
{
    #[Route('/active-campaign', name: 'app_webhook_active_campaign')]
    public function activecampaign(Request $request, LoggerInterface $logger): Response
    {
        $key = $this->getParameter('d360.apikey');
        $template = $this->getParameter('d360.template');
        $namespace = $this->getParameter('d360.namespace');
        $language = $this->getParameter('d360.language');
        $phoneNumber = $this->getParameter('d360.phoneNumber');
        $headerImage = $this->getParameter('d360.headerImage');
        $defaultSend = $this->getParameter('d360.defaultSend');

        $all = $request->request->all();

        if(isset($all['contact']) && is_array($all['contact'])) {
            $contact = $all['contact'];
//            $id = $contact['id'];
//            $email = $contact['email'];
//            $last_name = $contact['last_name'];
//            $ip = $contact['ip'];
//            $tags = $contact['tags'];
            $phone = $contact['phone'];
            $first_name = $contact['first_name'];
            $placeholders = [$first_name];
            if ($phone){
                $messageToSend = [];
                $messageToSend['apiKey'] = $key;
                $messageToSend['template'] = $template;
                $messageToSend['parameters'] = $placeholders;
                if ($defaultSend) {
                    $messageToSend['phoneTo'] = $phoneNumber;
                }else{
                    $messageToSend['phoneTo'] = $phone;
                }
                $messageToSend['language'] = "es";
                $messageToSend['templateDto'] = [];
                $messageToSend['templateDto']['id'] = $namespace;
                $messageToSend['templateDto']['header'] = [];
                $messageToSend['templateDto']['header']['link'] = $headerImage;
                $messageToSend['templateDto']['header']['format'] = 'IMAGE';
                $messageToSend['placeholders'] = $placeholders;


                $dialog360Service = new Dialog360Service($logger);
                $r = $dialog360Service->sendTemplateMessage($messageToSend, "es");
            }
        }

        return $this->render('webhook/index.html.twig', [
            'controller_name' => 'WebhookController',
        ]);
    }
}
