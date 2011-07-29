HOW TO : PARSER
===============


PARSER
------

Le Parser désigne un mécanisme, implémenté dans la classe Patchwork_PHP_Parser
du fichier **Parser.php**, qui va permettre de réaliser la tokenisation 
d'un fichier, c'est-à-dire de l'analyser token par token tout en associant à
chaque token une étiquette (voir http://li.php.net/manual/fr/tokens.php
pour les étiquettes).
Il permet via la manipulation des étiquettes liées au token d'obtenir des
informations, d'apporter des modifications précises sur un fichier à l'aide
de plugins qui sont en fait des classes héritières à celle du Parser.


PATCHWORK ET LES PARSERS
------------------------

Le Parser dans Patchwork est utilisé par le mécanisme d'autoloading contenu 
dans le fichier **Autoloader.php**, en fait c'est le préprocesseur qui fait
appel aux classes via l'autoload et lance le parser et tous les plugins
associés, ainsi le fichier est parsé avant son inclusion, utilisation ou 
autre. 

Le PHP étant un langage qui se compile à chaque requête du côté serveur,
certaines constantes natives à PHP faisant référence à des valeurs précises
nécessites qu'à chaque compilation, PHP aille chercher la valeur de ses
variables , or avec le parser, les constantes sont remplacées par leur
valeur avant la compilation, ce qui permet un gain de temps et une augmentation 
de la performance (micro-optimisation). Aussi il est nécessaire d'utiliser 
le Parser pour les constantes dynamiques (« magiques ») avant compilation 
sinon Patchwork ne fonctionnera pas. 

   Exemple : À sa compilation par php la constante magique "__FILE__"
   est remplacée par la valeur située dans le cache qui peut être différente de la
   valeur du fichier source, or ce n'est pas ce qu'on souhaite, ainsi en utilisant le 
   parser adéquate, "__FILE__" est remplacée par sa valeur d'après le fichier source. 

Le parser peut également être utilisé pour la portabilité, en particulier
lorsqu'une syntaxe a été supprimée d'une version à l'autre de php. 

En fait le fichier **Parser.php** dans lequel est contenu le mécanisme du parser
ne fait pas grand chose seul, il sert plutôt d'environnement de base pour les
plugins qui vont venir se greffer à lui et utiliser des outils prédéfinis. 

##A. Comment la tokenisation s'effectue-t-elle ?

Lorsque le Parser reçoit un fichier php en paramètre, il décompose chaque groupe
de caractères en un token qu'il identifie à une étiquette native à php, dans le
cas où le token ne possède pas d'étiquettes dans php, le token lui-même fait
office d'étiquette.

Exemple:	

   En entrée : array();

   Sortie    : 		Source code   Token type
   					array			T_ARRAY
   					(			    (	
					)			    )
   
   par l'exemple on voit que l'étiquette associée au token array est T_ARRAY, par
   contre le token "(" a pour étiquette lui-même.

Le résultat de la tokenisation est stocké dans un tableau que l'on peut
parcourir à l'aide des variables $lastType pour le token précédent et $penuType
l'avant dernier token, et de la méthode <code>&getNextToken();</code> pour le
token suivant.

Par la suite nous verrons qu'il est possible d'ajouter des étiquettes à un token
notamment à l'aide de la méthode <code>createToken();</code>.

##B. Comment parser un fichier

#####1. 
Créer et ouvrir un fichier **parser.php**

#####2. 
Inclure le fichier **Parser.php** en tapant <code>require 'le chemin du fichier'
</code>, si vous vous trouvez dans le dossier Patchwork, insérer cette ligne de
code directement : <code>require './class/Patchwork/PHP/Parser.php';</code>

#####3. 
On récupère dans une variable le contenu du fichier que l'on va parser avec la
fonction <code>$contenu = file_get_contents('Nom du fichier cible');</code>

#####4. 
On instancie la classe, par défaut la syntaxe suivante suffit :
   <code> $parser = new Patchwork_PHP_Parser; </code>

#####5. 
On appelle la méthode <code>parse($contenu)</code>. On peutrécupérer le résultat
dans une variable afin de l'injecter dans un fichier en suivant la syntaxe
suivante : <code> file_put_contents('fichier cible', buffer); </code>

#####6. 
Pour éxécuter le parser <code> php parser.php </code> dans un terminal. Dans le
cas où vous avez choisi de rediriger le résultat dans un fichier, vous pouvez
l'ouvrir avec votre éditeur de texte et constatez les modifications apportées.

   À ce stade, vous ne devriez voir aucunes modifications dans le fichier cible
   et c'est normal, pourtant la tokenisation a bien eu lieu juste qu'elle n'est
   pas visible. 

##C. Comment voir la tokenisation

   Si vous ne possédez pas le fichier de parser **Dumper.php**, sinon aller
   directement au grand D : 

   - Revenir à l'étape 2, remplacer dans le require, le chemin du fichier
   **Parser.php** par celui du fichier **Dumper.php**

   - Sauter l'étape 3, à l'étape 4 instancier la classe, par défaut la
   syntaxe suivante <code> new Patchwork_PHP_Parser_Dumper($parser); </code>.

   - Exécuter le parser comme indiqué au 6. Cette fois-ci devrait être affiché
   à l'écran avec une mise en page similaire à celle donnée en exemple plus
   haut.

##D. Comment créer un plugin pour le parser

Le but ce cette partie est de vous permettre de créer votre propre plugin, en
effet comme il a été dit plus haut, le parser est un mécanisme auquel il faut
lui rajouter des fonctions lui disant quoi faire. Le plugin représente ces
fonctions.

Comme partout en programmation, avant de se lancer dans le codage, il y a une
étape de conceptualisation du problème à passer. Cette étape étant propre à
chaque problème et personne, elle peut prendre un certain temps. Nous allons
donc procéder à la résolution d'un problème que vous suivrez étape par étape.

####1. Le Problème :

Dans php 3.4 une nouvelle syntaxe sera implétementé. Un tableau pourra se
déclarer de cette manière : <code>$a=[1,4,5];</code>. Or cette syntaxe
n'est pas valide pour les anciennes versions. On va donc créer un plugin qui
devra implémenter cette syntaxe. 

L'exercice consiste donc à écrire un plugin qui va transformer la syntaxe
<code>$a=[1,3,4];</code> en <code>$a=array(1,2,3);</code>. 

######A Bien cibler le problème

Il est question de remplacer le token "[" par les tokens "array" et "(" puis les
tokens "]" par des ")". Donc une première étape consistera à identifier lors de
la tokenization les tokens "[" et "]", et la deuxième étape, remplacer la
syntaxe crochet par la syntaxe array(). 

Vous vous êtes sûrement posés la question, mais comment peut-on être sûr qu'un
crochet fait référence à un tableau et pas à autre chose ?

Et vous avez raison, c'est une question importante puisque la réponse à cette
interrogation va déterminer l'algorithme à implémenter. La première idée que 
l'on peut avoir, c'est qu'il suffit de faire une liste exhaustive des situations
dans lesquelles on est sûr que seul un tableau peut-être déclaré et d'en exclure
toutes les autres. Et donc il suffirait de regarder le token précédent et dans
certains cas, l'avant dernier pour être fixé.

Cette méthode pourrait fonctionner, mais elle est trop coûteuse en temps car
elle nécessite de connaître toutes les situations dans lesquelles un tableau
peut-être déclaré sans déclencher d'erreurs. En effet il est plus simple et plus
rapide de penser dans l'autre sens. Notre préoccupation n'est pas d'obtenir un
code juste en sortie, cela relève de la responsabilité du codeur, mais juste 
de permettre l'intégration d'une syntaxe. Aussi plutôt que de s'intéresser au
cas où il faudrait un tableau, il est plus pertinent de s'intéresser au cas où
l'utilisation du crochet ne constitue pas une Parse error, déjà parce que le
nombre de possibilités d'utilisations est très faible par rapport à celui du
tableau et aussi parce qu'en réfléchissant comme ça nous sommes sûrs d'avoir la
liste exhaustive des cas d'utilisation d'un tableau et tous les autres cas
constituraient des Fatal error qui relèveraient du codeur. 

Et par une série de test sur tous les tokens connus (voir lien première partie)
on a déterminé (faîtes nous confiance) que le seul token pouvant précéder le 
token "[" est "T_VARIABLE".  

######B Les outils de l'implémentation

Maintenant que nous avons déblayé et saisi le problème, nous allons pouvoir
implémenter l'algorithme mais avant cela il est nécessaire que certaines
fonctionnalités incluses dans le fichier **Parser.php** vous soient expliquées.

<code>$callbacks;</code> : La variable callbaks est un array particulier,
c'est elle qui va contenir les méthodes et les tokens associés. Cette variable
fonctionne comme un marqueur de token, c'est à dire que le plugin dit au parser
, je veux tous les tokens "[" et "]" et à chaque tokens il applique la méthode
associée.

exemple de syntaxe : <code>$callbaks = array( 'tagOpenBracket' => '[' );</code>. 
 
'tagOpenBracket', désigne la méthode et '[', le token sur lequel la méthode doit
s'appliquer.

<code>$lastType;</code> : Permet d'accéder au prédécent token.

<code>$unshiftTokens();</code> : Sûrement la méthode la plus importante pour
nous car c'est elle qui va insérer de nouveaux tokens à la position suivante au
moment de son appel. 

exemple de syntaxe : <code>$unshiftTokens(array(T_ARRAY, 'array'), '(');

le nombre d'arguments désignent le nombre de tokens à insérer, on remarque dans
l'exemple que le premier argument est un array et pas le second. En fait comme
il a été précisé dans la présentation du parser, certains tokens n'ont pas 
d'étiquette, le token faisant office d'étiquette, par contre tous les tokens
possédant une étiquette que l'on souhaite insérer doivent être associés à l'aide
d'un array à une étiquette, par défaut celle native à php (voir
http://li.php.net/manual/fr/tokens.php).

Si vous l'utilisez de cette manière <code>return $unshiftTokens((array(T_ARRAY, 
'array'));</code> alors avant d'insérer le token 'array',
<code>$unshiftTokens</code> supprimera le token présent à la position où elle
est appelée.

Donc pour nous il s'agira d'utiliser <code>$unshiftTokens();</code> dans des
méthodes déclenchées par les tokens "[" et "]". 

À ce stade vous possédez tous les outils modulo les notions de php pour créer le
plugin. 

######C Le Code







