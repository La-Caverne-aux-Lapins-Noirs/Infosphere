-- Migration des bases existantes : le schéma de création courant contient déjà
-- log_as, mais les bases créées avant son ajout conservent l'ancien SET.
ALTER TABLE `parent_child`
  MODIFY `relation` SET('financial','legal','emergency','internship','log_as')
  NOT NULL DEFAULT '';
