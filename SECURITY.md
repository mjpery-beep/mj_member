# Politique de securite du plugin MJ Member

## Signalement responsable
Merci de signaler toute vulnerabilite potentielle via un courriel prive a l'equipe mainteneur du plugin MJ Member (coordonnees indiquees dans le depot). Evitez toute divulgation publique tant qu'un correctif n'est pas disponible.

Lors du signalement, fournissez :
- Un resume de la faille et de son impact possible (exfiltration de donnees, elevation de privileges, etc.).
- Les pre-requis (roles utilisateurs, configuration, contexte WordPress) et les etapes precises pour reproduire le probleme.
- Des preuves de concept ou extraits de logs pertinents.
- Vos coordonnees pour un suivi.

## Engagement de reponse
- Accuse de reception sous 3 jours ouvrables.
- Analyse et plan d'action communiques sous 10 jours ouvrables.
- Corrections diffusees via une mise a jour du plugin avec notes de version explicites.

## Portee et versions supportees
Nous assurons les correctifs de securite sur la derniere version majeure du plugin MJ Member et sur la version immediatement precedente si encore largement deployee. Les installations plus anciennes doivent etre mises a niveau.

## Recommandations d'exploitation
- Limitez l'acces administrateur WordPress aux comptes necessaires et appliquez les capacites definies dans `includes/core/capabilities.php`.
- Stockez les cles Stripe et autres secrets dans des variables d'environnement ou le fichier de configuration protege; ne les committez jamais.
- Activez HTTPS sur l'ensemble du site et securisez les webhooks Stripe (tetes signees).
- Conservez l'en-tete protecteur `if (!defined('ABSPATH')) exit;` sur tous les fichiers charges par WordPress.
- Examinez regulierement les comptes membres et les journaux (`wp-content/debug.log` ou solutions equivalent).
- Mettez en place des sauvegardes et surveillez les modifications des tables `mj_*` via les outils de la plateforme d'hebergement.

## Contact d'urgence
En cas de faille critique affectant des donnees sensibles ou un usage malveillant en cours, ajoutez "[SECURITE]" dans l'objet de votre message pour prioriser le traitement.

Merci d'aider a garantir la securite du plugin MJ Member.
