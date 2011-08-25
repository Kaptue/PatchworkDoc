PARSER 
======

Parmi les outils originaux et puissants de Patchwork, on peut compter le
Parser.

Le Parser désigne un mécanisme qui va permettre de parser un fichier: la
tokenization, c'est-à-dire de l'analyser token par token (un token peut-être un
caractère, une chaîne de caractères), tout en déterminant l'étiquette de chaque
token (voir http://www.php.net/manual/fr/tokens.php pour plus d'informations
sur les étiquettes). Lorsqu'on souhaite apporter des modifications à un fichier
à certains moments non-prédéterminées ou alors des modifications spécifiques à
une circonstance, le Parser est un moyen idéal d'y parvenir sans modifier pour
autant la structure du fichier.  Mais le Parser seul ne fait pas grand chose,
il faut lier son utilisation à des plugins (des scripts php), ainsi il existe
selon les modifications que l'on souhaite apporter un ensemble de "plugins" à
appliquer sur le fichier cible. La procédure de tokenization est la suivante :
on sélectionne un fichier dont on prélève le contenu, on indique la
modification à effectuer et on l'applique au fichier. L'utilisation du Parser
peut donc servir principalement à plusieurs choses selon les plugins qui lui
sont associés.

##PATCHWORK ET LE PARSER

Le Parser dans Patchwork est utilisé par le mécanisme d'autoloading contenu
dans le fichier **Autoloader.php**, en fait c'est le préprocesseur qui fait
appel aux classes via l'autoload et lance le Parser, ainsi le fichier est parsé
avant son inclusion, utilisation ou autre.

Le PHP étant un langage se compilant à chaque requête du côté serveur,
certaines variables natives à PHP faisant référence à des valeurs précises, à
chaque compilation, PHP doit aller chercher la valeur de ses variables, or avec
le parser, les variables sont remplacées par leur valeur avant la compilation,
ce qui permet un gain de temps et une augmentation de la performance
(micro-optimisation). Aussi Patchwork est construit de telle façon qu'il est
nécessaire d'utiliser le Parser pour les constantes dynamiques (« magique »)
avant compilation sinon Patchwork ne fonctionnera pas.

Exemple : À sa compilation par php la constante magique <code>__FILE__</code>
est remplacée par la valeur située dans le cache, or ce n'est pas ce qu'on
souhaite, ainsi en utilisant le parser adéquate, <code>__FILE__</code> est
remplacée par sa valeur d'après le fichier source avant compilation.

Dans patchwork on distingue deux types de plugins de parser. Les parsers
spécifiques à Patchwork et les parsers indépendants de Patchwork mais
améliorant son fonctionnement, utilisation.

###LE PARSER EN DETAIL

Comme dit en introduction le Parser désigne un mécanisme de tokenization, ce
mécanisme est en fait contenu dans la classe Patchwork_PHP_Parser du fichier
**Parser.php**. Donc lorsqu'on parle de la tokenization, on fait indirectement
référence à ce fichier.  Or la tokenization est un mécanisme existant en dehors
de Patchwork et déjà implémentée via d'autres fonctions, donc ce mécanisme
n'est pas nouveau et encore moins original pourtant avec Patchwork le choix a
été fait de lui ajouter un processus de tokenization personnalisée. Il est donc
nécessaire de voir quelles sont les originalités de cette implémentation et
comment elles font du Parser un outil clef de Patchwork. Aussi dans cette
partie nous détaillerons le fonctionnement du fichier **Parser.php**.

Pour distinguer les tokens on a crée deux constantes, "T_SEMANTIC" égale à 0
qui correspond à tous les tokens sémantiques et "T_NON_SEMANTIC" égale à 1,
pour les tokens non sématiques (les espaces blancs et commentaires). Puis on
rajoute 3 étiquettes de tokens "T_CURLY_CLOSE" en référence aux étiquettes
"T_CURLY_OPEN" et "T_DOLLAR_OPEN_CURLY_BRACES", "T_KEY_STRING" et
"T_UNEXPECTED_CHARACTER" pour les caractères inattendus. 

**Interface de la classe Patchwork_PHP_PARSER**

Le parser est crée dans l'optique d'offrir plus de maniabilité sur les chaînes
de caractères que la tokenization classique, ainsi il dispose d'une interface
assez fournie.

Parmis les plugins utilisant l'environnement du Parser, il existe parfois une
dépendance entre différents plugins, cette dépendance est gérée par les
variables <code>$depencyName</code> et <code>$dependencies</code>.

Les plugins sont implémentés pour répondre à une situation donnée. Et c'est le
tableau <code>$callbacks</code> qui permet de rensigner le Parser quant au
moment où il doit éxécuter une méthode ou non. (Voir tutoriel howToParser To :
Plugin For Parser pour plus d'informations sur l'utilisation).

Les autres variables permettent de manipuler des tokens, soit en les
parcourant, soit en ajoutant de nouvelles étiquettes, soit en donnant des
informations sur tel ou tel tokens. Comme par exemple <code>$tokens, $types,
$texts, $tokenRegistry</code> qui sont des tableaux. le premier pour stocker
les tokens a parsé, les second et troisième pour contenir les tokens déjà
parsés l'un les types sémantiques, l'autre les types non sémantiques, et enfin
le quatrième pour gérer les étiquettes associés à la variable
<code>$callbacks</code>.

**la fonction __construct**

Comme pour toutes les classes, le rôle de la fonction constructeur est de
construire l'objet, c'est-à-dire de le préparer à sa première utilisation.
Pour le cas du Parser, le constructeur va en plus d'initialiser les propriétés,
vérifier si les dépendances de l'objet instancié ont déjà été instanciées et
conserver la chaîne parsée triée pour la variable pour la variable
<code>$callbacks</code>.

**Les méthodes**

Ici vont être décrites les méthodes implémentées dans le fichier Parser, celles
qui manipulent la liste de tokens en fonction des plugins appelés. 

**<code>parse()</code>** : C'est elle qui réalise la tokenization améliorée, en
éxécutant les méthodes <code>getTokens</code> et <code>ParseTokens</code>

**<code>getErrors()</code>** : récupère les erreurs émises lors de la
tokenization.

**<code>getTokens()</code>** : C'est une version améliorée de token_get_all(),
elle corrige un bug présent dans l'utilisation de token_get_all(), en effet
avec une version de php inférieure à la 5.3.0, la fonction mange certains
caractères non permis et léve une erreur de syntaxe sur ce caractère, de plus
lorsqu'elle est utilisée sur un code contenant la fonction
<code>__halt_compiler()</code>, elle analyse quand même les caractères situés
après <code>__halt_compiler()</code> en les corrompant.
<code>getTokens()</code> réintroduit ainsi les caractères supprimés par
<code>token_get_all();</code>.

**<code>parseToken()</code>** : Cette méthode récupère le tableau de tokens
<code>$this->tokens</code>, puis apporte des modifications en ajoutant au
tableau de tokens, les nouvelles étiquettes crées via la méthode
<code>CreateToken();</code> liées à la résolution des variables interpolées. En
effet une variable interpolée dans le parser peut s'écrire de cette façon
<code>echo "test $a[c]"</code>, lors d'une tokenization classique, le token "c"
aura l'étiquette T_STRING, qui est une étiquette bien vague tant on connaît le
nombre de token ayant comme étiquette T_STRING, donc l'intêret ici est
d'augmenter la précision de l'étiquette d'un token afin de le rendre
indépendant du contexte d'où l'ajout de l'étiquette T_KEY_STRING qui remplacera
dans ce cas, l'étiquette T_STRING du token "c". De plus on souhaite ajouter
pour plus précisions l'étiquette "T_CURLY_CLOSE" or une variable peut se
déclarer de cette façon également <code>echo "test {$a}";</code> ou encore
<code>echo "test ${a}";</code>, donc il s'agit aussi de ne pas oublier de
rajouter l'étiquette T_CURLY_CLOSE. La méthode gère également la variable
<code>$callbacks</code> possédant les méthodes à appliquer pour un token donné
(voir tutoriel howToParser To : Plugin For Parser pour plus d'informations sur
son utilisation).

**<code>setError()</code>** : Affiche une erreur contenu dans le code donné en
entré dans le parser.

**<code>register()</code>** et **<code>unregister()</code>** : Ces deux
méthodes envoient à la méthode <code>registryApply()</code>, les méthodes du
plugin à ajouter (pour la première) ou à supprimer (pour la deuxième) à la
variable <code>$callbacks</code>.

**<code>getNextToken()</code>** : Cette méthode permet d'accéder au token
suivant.

**<code>unshiftTokens</code>** : C'est elle qui va permettre d'injecter des
tokens dans le flux d'entreé de la tokenization. Elle retourne false.
Lorsqu'elle est utilisée en valeur de retour par une fonction, elle supprime
également le token situé à la position où elle est appelée. (voir tutoriel
howToParser pour plus d'informations sur son utilisation).

**<code>registryApply</code>** : Méthode utilisée par les méthodes
<code>register()</code> et <code>unregister()</code>, en fonction de son
deuxième paramètre qui doit être soit le booléen true ou false, elle ajoutera
ou supprimera la méthode de la variable <code>$callbacks</code>.

**<code>CreateToken()</code>** : Comme son nom l'indique cette méthode est
utilisée pour créer des étiquettes de tokens qui seront ajoutées à l'étiquette
native des tokens. Les étiquettes non natives à php sont précédées dans le flux
des étiquettes par une virgule.

###CONCLUSION

Comme il a été dit dans l'introduction et mis en évidence par le détail des
méthodes implantées dans le fichier **Parser.php** contenant le mécanisme de
tokenization, le Parser n'est pas un simple outils de tokenization. En effet,
il dispose d'un ensemble d'élèments offrant une nouvelle façon de voir la
tonkenization puisqu'il enrichit les étiquettes de tokens présentes dans php en
les rendant encore un peu plus indépendant du contexte, ce qui permet
d'apporter toutes sortes de modifications et cela de façon très précises, il
est également utile puisqu'il permet de signaler les erreurs qui peuvent être
contenues dans un code ou survenir durant la tokenization. De plus le Parser
répond parfaitement au soucis de portabilité puisqu'il peut fonctionner
indépendemment du Patchwork.  C'est donc un véritable environnement crée pour
les plugins qui vont s'y greffer.	

##LES PLUGINS

Le Parser pour véritablement fonctionner a besoin de plugin qui vont se greffer
à lui. Donc dans Patchwork, le Parser est fourni avec un certain nombre de
plugins. Certains pour améliorer la lisibilité du code, d'autres pour ajouter
ou supprimer une syntaxe obsolète, d'autres pour ajouter des étiquettes afin
d'augmenter la précision de lecture des tokens. (Voir le tutoriel howToParser :
Plugin For Parser pour avoir un exemple de plugin utilisant le Parser)

Parmis les plugins fournis, il existe deux types de plugins :
- Ceux indépendant de Patchwork, c'est-à-dire qui peuvent être éxécuter sans
  avoir Patchwork.

- Ceux dépendant de Patchwork, ils ont été crées avec une certaine spécifité. 

Les plugins indépendants de Patchwork vont bénéficier d'une description un peu
plus détaillée et de fichier test prêt à être éxécutée. (Voir le tutoriel
howToParser pour avoir un exemple d'éxécution des plugins)
