# IliasTraxEventBridge v0.1.5

Version de debug pour ILIAS 10.

Objectif : rendre le plugin visible/activable et journaliser les événements ILIAS reçus via le slot EventHook.

## Correction v0.1.5

- `ilIliasTraxEventBridgeConfigGUI` étend maintenant `ilPluginConfigGUI`.
- Ajout de la directive ilCtrl obligatoire pour les écrans de configuration des plugins ILIAS 8+ :
  `@ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI`
- Suppression de la dépendance au formulaire legacy `ilPropertyFormGUI` dans l'écran de configuration.

## Installation

Copier le dossier dans :

```bash
public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Puis exécuter depuis la racine ILIAS :

```bash
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Ensuite : Administration > Extending ILIAS > Plugins > Update / Activate.


## Changements 0.1.5

- Affichage du `payload_json` dans l'écran Configure.
- Pré-classification des événements candidats xAPI.
- Détection de `ref_id` depuis l'URL quand l'événement ne le transmet pas dans ses paramètres.
- Détection indicative du type d'objet depuis `cmdClass` pour certains écrans ILIAS.


## 0.1.5

Amélioration de l'écran de configuration :
- tableau lisible avec retour à la ligne dans les cellules ;
- colonnes regroupées pour éviter l'étirement horizontal ;
- résumé du nombre total d'événements journalisés ;
- affichage explicite du nombre d'événements affichés sur la limite de 100 ;
- payload JSON formaté dans un bloc dépliable ;
- URI affichée dans un bloc scrollable.
