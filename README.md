# Router

[![Build Status](https://travis-ci.org/AkibaTech/Router.svg?branch=master)](https://travis-ci.org/AkibaTech/Router) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/646acaa9-b90b-4d71-b6e3-ebe9a377b622/mini.png?branch=master)](https://insight.sensiolabs.com/projects/646acaa9-b90b-4d71-b6e3-ebe9a377b622) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/AkibaTech/Router/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/AkibaTech/Router/?branch=master)

Router est un petit routeur de requête HTTP écrit en PHP.  

Il sera votre compagnon idéal si vous avez besoin d'une librairie simple et efficace ou si vous recherchez une **base simple à comprendre**.  
Sinon, regardez du côté des merveilleuses librairies [symfony/routing](https://symfony.com/doc/current/components/routing.html) ou encore [league/route](http://route.thephpleague.com/).  

![English doc](https://raw.githubusercontent.com/gosquared/flags/master/flags/flags/flat/16/United-Kingdom.png) English documentation [is here](README.en.md).

## Installation

L'installation se fait idéalement via composer.  
`composer require akibatech/router dev-master`

Ou sinon, copiez le fichier **src/Router.php** où vous le souhaitez et faites en un **require**.

## Configurer Apache

Le routeur fonctionne très bien avec tout type d'URL. Mais si vous faites de l'URL rewriting, vous pouvez créer ce fichier .htaccess.

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

## Utilisation

### Instanciation

Router ne prend aucun paramètre, instanciez le de cette façon:

```php
$router = new Router;
```

### Ajouter des routes

Une fois l'instance en main, ajouter des routes est très simple.  

```php
// Répondra à : /hello (en GET)
$router->get('hello', function () {
	echo 'Hello world!';
});
```

Il est aussi possible d'ajouter des règles en **POST**, **PUT**, **PATCH** et **DELETE**.

```php
// Requête POST
$router->post(...);
// Requête PATCH
$router->patch(...);
```

Le routeur peut répondre à tous les verbes ou un ou plusieurs verbes HTTP :  

```php
// Requêtes GET et POST
$router->add(['GET', 'POST'], 'hello', ...);
// ... Ou bien, tout type de requêtes
$router->any('hello', ...);
```

Finalement, les routes peuvent être chainées ou ajoutées via un callback :

```php
// A la chaîne
$router->get('foo', ...)->get('bar', ...);
// ...ou via un callback
$router->routes(function ($router) {
	// Votre logique
});
```

Si aucun callback n'est donné à routes(), l'ensemble des routes sera retournée.

```php
$router->get('hello', ...);

var_dump($router->routes()); // Retourne [1 => ['uri' => 'hello', 'action' => '...']]
```

### Ecouter les requêtes

Pour que tout fonctionne, il ne manque plus qu'à ce que le routeur écoute les requêtes entrantes :

```php
// Utilise par défaut REQUEST_URI et REQUEST_METHOD
$router->listen();
// Vous pouvez fournir au router l'URI et la méthode (via une autre librairie par exemple)
$router->listing('request', 'method');
```

### Dispatcher les routes

Evidemment, à chaque route, un besoin. A vous de définir une action pour chacune d'entre elle.


- Un callback :

```php
$router->get('hello', function () {
	echo 'Hello!';
})
```

- Une classe (controller, ...) :

```php
$router->get('hello', 'MaClasse@maMethode');
```

Ici, la route, une fois matchée par le routeur instanciera la classe nommée "MaClasse" et appelera la méthode "maMethode".
Notez que le routeur **accepte les namespaces** à partir du moment où le reste de votre application est capable de les autoloader (PSR-4, ...).

```php
$router->get('route-namespace', 'App\Http\Controller@maMethode');
```

D'ailleurs, vous pouvez définir un namespace global à toutes vos actions.

```php
// Appellera App\Http\Controller\Account@resume()
$router->namespaceWith('App\Http\Controller')->get('mon-compte', 'Account@resume');
```

Il est également possible de définir une action quand aucune route n'a été matchée.

```php
$router->whenNotFound(function () {
    echo 'Page non trouvée';
});
```

### Paramètres d'URL

Vos routes peuvent contenir des paramètres dynamique. Leur utilisation est très simple.

```php
// Autorise "profil/1" ou "profil/12" mais pas "profil/john".
$router->get('profil/{:num}', ...);
```

Les paramètres peuvent être :  

- {:num} pour un nombre uniquement
- {:alpha} pour des lettres uniquement
- {:any} pour tout type de caractères
- {:slug} pour les nombres, les lettres et le tiret (-)

Les paramètres sont ensuite envoyés à l'action de la route dans l'ordre définie dans l'URL.  

```php
$router->get('profil/{:num}/{:alpha}', function ($id, $name) {
	echo "Mon nom est $name et je porte l'ID n°$id !";
});
```

### Nommer les routes

Il vous est possible de **nommer vos routes** afin d'y accèder plus rapidement pour créer des liens (dans vos vues par exemple).  

```php
$router->get('/homepage', '...', 'home');
$router->link('home'); // Retourne "/homepage"
```

Si votre route contient des paramètres, il est possible de récupérer l'URI avec des paramètres.  
Le nombre de paramètre passé à la méthode doit être le même que ceux attendus dans l'URI, auquel cas, une erreur sera levée.

```php
$router->get('/tag/{:slug}', '...', 'tag');
$router->link('tag', 'wordpress'); // => Retourne "/tag/wordpress"

$router->get('/user/{:num}/{:any}', '...', 'profile');
$router->link('profile', [42, 'JohnDoe']); // => Retourne "/user/42/JohnDoe"
```

### Mise en cache

Parfois, lorsque notre Router contient beaucoup de route, il est dommage de le configurer intégralement à chaque éxécution du script. 
Router supporte bien la **serialization** et la **deserialization**. Deux méthodes existent pour vous aider dans cette tâche.

- Exporter l'instance avec ses routes :

```php
$compiled = $router->getCompiled(); // Retourne un string
```

- Importer l'instance :

```php
$router = Akibatech\Router::fromCompiled($compiled); // Retourne l'instance précédemment compilée
```

Notez que les **routes utilisant un callback n'est pas supporté**. Seule la notation "MaClasse@maMethode" l'est.  
Également, Router ne propose pas de fonctionnalité permettant de stocker ou de lire le fichier compilé. Ce n'est pas son but. 

## Tests

Les tests sont effectués avec PHPUnit 5.5. Si vous n'avez pas **phpunit** installé globalement, ils peuvent l'être via **vendor/bin**.

```bash
vendor/bin/phpunit
```

Les tests ne sont pas encore tous en place. N'hésitez pas à contribuer.

## Contribuer

Le routeur est très simple pour le moment et n'a pas été poussé dans ses derniers retranchements.  
N'hésitez pas à proposer vos améliorations !

## Licence

MIT
