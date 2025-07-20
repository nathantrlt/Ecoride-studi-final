# Projet Ecoride

## Documentation de Déploiement Local

### 1. Introduction

Ce document décrit la procédure à suivre pour déployer l'application web Ecoride dans un environnement de développement local. Ce guide est destiné à faciliter la mise en place rapide d'un environnement de travail fonctionnel pour le développement, les tests et la maintenance de l'application.

L'environnement de production est hébergé sur **AWS Lightsail**. Le déploiement local vise à reproduire un environnement similaire pour assurer la compatibilité.

### 2. Prérequis Techniques

Les composants logiciels suivants sont nécessaires pour le déploiement local. Assurez-vous qu'ils sont installés et configurés sur votre poste de travail :

*   **Système d'Exploitation :** Compatible avec les outils listés ci-dessous (Windows, macOS, Linux).
*   **Serveur Web :** Apache 2.4+ ou Nginx 1.18+.
*   **PHP :** Version 7.4 ou supérieure. Les extensions PHP requises incluent a minima `mysqli` ou `pdo_mysql` pour la base de données. D'autres extensions pourraient être nécessaires en fonction des fonctionnalités utilisées (ex: `curl`, `json`, `gd`).
*   **Base de Données :** Serveur MySQL 5.7+ ou MariaDB 10.2+.
*   **Gestionnaire de Dépendances PHP :** Composer 2.x+.
*   **Système de Gestion de Version :** Git 2.x+.

### 3. Structure du Projet

Le dépôt contient les principaux répertoires et fichiers suivants :

*   `/`: Répertoire racine du projet.
*   `/backend/`: Contient les scripts PHP côté serveur (gestion des utilisateurs, véhicules, covoiturages, etc.).
*   `/css/`: Fichiers CSS pour la mise en forme.
*   `/js/`: Fichiers JavaScript pour les interactions côté client.
*   `/vendor/`: Dossier géré par Composer contenant les dépendances PHP.
*   `index.html`: Page d'accueil principale.
*   `README.md`: Ce document.
*   `composer.json`: Fichier de configuration de Composer.

*(Cette liste est indicative. Adaptez-la à la structure réelle de votre projet si elle est différente.)*

### 4. Procédure de Déploiement Local

Suivez les étapes ci-dessous pour configurer votre environnement local :

**Étape 1 : Cloner le Dépôt**

Ouvrez une interface en ligne de commande (terminal, Git Bash, etc.) et exécutez la commande suivante pour cloner le code source depuis le dépôt distant :

```bash
git clone https://github.com/nathantrlt/Ecoride-final.git
cd Ecoride-final
```

**Étape 2 : Installer les Dépendances PHP**

Naviguez dans le répertoire racine du projet (si vous n'y êtes pas déjà) et utilisez Composer pour télécharger et installer les bibliothèques PHP requises :

```bash
composer install
```

**Étape 3 : Configuration de la Base de Données**

a.  **Création de la Base de Données :**
    Accédez à votre serveur de base de données local (via la ligne de commande, phpMyAdmin, MySQL Workbench, etc.) et créez une base de données dédiée à l'application. Il est recommandé d'utiliser le même nom que celui utilisé en production pour minimiser les différences de configuration.

```sql
CREATE DATABASE ecoride_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

*(Remplacez `ecoride_db` si vous souhaitez un nom différent.)*

b.  **Importation du Schéma et des Données :**
    Si vous disposez d'un fichier d'exportation de la base de données de production (dump SQL), importez-le dans la base de données locale que vous venez de créer.

```bash
mysql -u Nathan -p ecoride_db < Dump20250720.sql
```

Si vous ne disposez pas d'un dump, exécutez les scripts SQL nécessaires pour créer les tables et structures de base de données.

c.  **Mise à Jour des Paramètres de Connexion :**
    Modifiez les fichiers de configuration ou les sections de code de l'application responsables de la connexion à la base de données pour qu'ils utilisent les identifiants et l'adresse de votre serveur de base de données local.

    Identifiez les variables ou constantes définissant `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` et mettez-les à jour. Exemple :

```php
define('DB_HOST', 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'); // Ou l'adresse de votre serveur BD local
define('DB_NAME', 'ecoride_db');
define('DB_USER', 'Nathan');
define('DB_PASSWORD', 'Af18PsKCc-');
```

**Étape 4 : Configuration du Serveur Web Local**

Configurez votre serveur web (Apache ou Nginx) pour qu'il serve les fichiers de l'application.

*   **Pour Apache :**
    Configurez un Virtual Host. La directive `DocumentRoot` doit pointer vers le répertoire racine du projet (`/chemin/vers/Ecoride-final`). Assurez-vous que le module PHP approprié (`mod_php` ou via FastCGI/FPM) est activé et configuré pour traiter les fichiers `.php`.

*   **Pour Nginx :**
    Configurez un bloc `server`. La directive `root` doit pointer vers le répertoire racine du projet. Définissez un bloc `location` pour les fichiers `.php` qui transmettra les requêtes à un processus PHP-FPM local.

Assurez-vous que votre configuration permet l'exécution des fichiers PHP et que les fichiers d'index (`index.html`, `index.php`) sont correctement définis.

**Étape 5 : Accès à l'Application**

Démarrez ou redémarrez votre serveur web local. Ouvrez un navigateur web et accédez à l'adresse configurée pour votre Virtual Host (par exemple, `http://localhost/`). L'application Ecoride devrait s'afficher.

### 5. Notes et Bonnes Pratiques

*   **Environnements :** Maintenez une séparation claire entre les configurations de l'environnement local et celles de production (Lightsail). N'utilisez jamais les identifiants de production dans votre code local.
*   **Gestion des Secrets :** Pour les informations sensibles (mots de passe BD, clés API), utilisez des variables d'environnement ou un fichier de configuration local non versionné par Git (`.env` ou similaire) plutôt que de les coder en dur.
*   **Synchronisation :** Pour maintenir votre environnement local à jour avec les dernières modifications du dépôt, utilisez `git pull`. Après un `pull`, exécutez `composer install` à nouveau pour vous assurer que toutes les dépendances sont à jour.

---

Ce document fournit les étapes fondamentales. En fonction de la complexité de votre application, des étapes supplémentaires (configuration de cron jobs, services externes, etc.) pourraient être nécessaires.
