# Router

Router est un petit routeur de requête HTTP écrit en PHP.  

Il fait le job, bien que pas encore testé. Utilisez le si vous avez besoin d'une **base simple à comprendre** ou si **votre besoin est minimal**. Sinon, regardez du côté des merveilleuses librairies [symfony/routing](https://symfony.com/doc/current/components/routing.html) ou encore [league/route](http://route.thephpleague.com/).

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

Router fonctionne autour d'un singleton et ne se construit pas directement. Appelez le ainsi :  

```php
$router = Akibatech\Router::getInstance();
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
$router->listen();
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

## Tests

Bientôt !

## Contribuer

Le routeur est très simple et pas encore testé. N'hésitez pas à proposer vos améliorations !

## Licence

MIT
