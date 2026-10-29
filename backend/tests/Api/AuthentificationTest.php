<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class AuthentificationTest extends ApiTestCaseRadar
{
    public function testConnexionRenvoieUnJetonJwt(): void
    {
        $this->creerUtilisateur('moi@exemple.test', motDePasse: 'motdepasse-solide');

        $reponse = $this->client->request('POST', '/api/connexion', ['json' => ['email' => 'Moi@Exemple.test', 'motDePasse' => 'motdepasse-solide']]);

        self::assertResponseIsSuccessful();
        $jeton = $reponse->toArray()['token'] ?? null;
        self::assertIsString($jeton);

        $this->client->request('GET', '/api/moi', ['auth_bearer' => $jeton]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => 'moi@exemple.test', 'admin' => false, 'roles' => ['ROLE_USER']]);
    }

    public function testMauvaisMotDePasse(): void
    {
        $this->creerUtilisateur('moi@exemple.test');

        $this->client->request('POST', '/api/connexion', ['json' => ['email' => 'moi@exemple.test', 'motDePasse' => 'faux']]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiProtegee(): void
    {
        foreach (['/api/moi', '/api/bons-plans', '/api/tableau-de-bord', '/api/economies', '/api/preferences'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(401, $url);
        }
    }

    public function testDocumentationPublique(): void
    {
        $this->client->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['info' => ['title' => 'Radar Bons Plans']]);
    }

    public function testInscription(): void
    {
        $this->client->request('POST', '/api/inscription', ['json' => ['email' => 'Nouveau@Exemple.test', 'motDePasse' => 'un-long-mot-de-passe', 'pseudo' => 'Nouveau']]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['email' => 'nouveau@exemple.test', 'pseudo' => 'Nouveau']);

        $this->client->request('POST', '/api/connexion', ['json' => ['email' => 'nouveau@exemple.test', 'motDePasse' => 'un-long-mot-de-passe']]);
        self::assertResponseIsSuccessful();
    }

    public function testInscriptionRefuseeDoublonEtMotDePasseCourt(): void
    {
        $this->creerUtilisateur('moi@exemple.test');

        $this->client->request('POST', '/api/inscription', ['json' => ['email' => 'moi@exemple.test', 'motDePasse' => 'un-long-mot-de-passe']]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'email', 'message' => 'Un compte existe déjà avec cette adresse e-mail.']]]);

        $this->client->request('POST', '/api/inscription', ['json' => ['email' => 'autre@exemple.test', 'motDePasse' => 'court']]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'motDePasse', 'message' => 'Le mot de passe doit contenir au moins 10 caractères.']]]);
    }
}
