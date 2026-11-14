-- Operator-maintained vehicle tariff class dictionary.
-- Only approved family aliases are seeded. Uncertain models are intentionally absent.
CREATE TABLE IF NOT EXISTS vehicle_tariff_class_models (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 class_number TINYINT UNSIGNED NOT NULL,
 family_name VARCHAR(100) NOT NULL,
 alias VARCHAR(120) NOT NULL,
 normalized_alias VARCHAR(120) NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_vehicle_class_alias(normalized_alias),
 KEY idx_vehicle_class_number(class_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO vehicle_tariff_class_models (class_number,family_name,alias,normalized_alias) VALUES
(1,'پراید','پراید','پراید'),(1,'پراید','pride','pride'),(1,'پراید','prayd','prayd'),
(1,'آردی و روآ','آردی','آردی'),(1,'آردی و روآ','اردی','اردی'),(1,'آردی و روآ','روآ','روآ'),(1,'آردی و روآ','روا','روا'),(1,'آردی و روآ','roa','roa'),(1,'آردی و روآ','rd','rd'),
(2,'سمند و سورن','سمند','سمند'),(2,'سمند و سورن','samand','samand'),(2,'سمند و سورن','سورن','سورن'),(2,'سمند و سورن','soren','soren'),
(2,'تیبا','تیبا','تیبا'),(2,'تیبا','tiba','tiba'),(2,'کوئیک','کوئیک','کوئیک'),(2,'کوئیک','quick','quick'),
(2,'اطلس','اطلس','اطلس'),(2,'اطلس','atlas','atlas'),
(2,'ساینا','ساینا','ساینا'),(2,'ساینا','saina','saina'),(2,'ساینا','saiana','saiana'),
(2,'پژو ۲۰۶','206','206'),(2,'پژو ۲۰۷','207','207'),(2,'پژو ۴۰۵','405','405'),
(2,'پژو پارس','پارس','پارس'),(2,'پژو پارس','pars','pars'),
(2,'رانا','رانا','رانا'),(2,'رانا','rana','rana'),
(2,'تندر ۹۰ / ال ۹۰','تندر','تندر'),(2,'تندر ۹۰ / ال ۹۰','توندر','توندر'),(2,'تندر ۹۰ / ال ۹۰','ال90','ال90'),(2,'تندر ۹۰ / ال ۹۰','l90','l90'),(2,'تندر ۹۰ / ال ۹۰','logan','logan'),
(2,'ام وی ام','ام وی ام','ام وی ام'),(2,'ام وی ام','mvm','mvm'),
(2,'ریو','ریو','ریو'),(2,'ریو','rio','rio'),
(2,'برلیانس','برلیانس','برلیانس'),(2,'برلیانس','brilliance','brilliance'),(2,'برلیانس','brillance','brillance'),
(2,'دنا','دنا','دنا'),(2,'دنا','dena','dena'),(2,'تارا','تارا','تارا'),(2,'تارا','tara','tara'),
(2,'آریو','آریو','آریو'),(2,'آریو','ario','ario'),(2,'لیفان','لیفان','لیفان'),(2,'لیفان','lifan','lifan'),
(2,'سهند','سهند','سهند'),(2,'سهند','sahand','sahand'),(2,'شاهین','شاهین','شاهین'),(2,'شاهین','shahin','shahin'),
(2,'جک','جک','جک'),(2,'جک','jac','jac'),(2,'چری','چری','چری'),(2,'چری','chery','chery'),
(2,'زانتیا','زانتیا','زانتیا'),(2,'زانتیا','xantia','xantia'),
(2,'چانگان','چانگان','چانگان'),(2,'چانگان','changan','changan'),
(2,'هیوندای','هیوندای','هیوندای'),(2,'هیوندای','hyundai','hyundai'),
(2,'جیلی','جیلی','جیلی'),(2,'جیلی','geely','geely'),
(3,'ون و مینی بوس','ون','ون'),(3,'ون و مینی بوس','van','van'),(3,'ون و مینی بوس','مینی بوس','مینی بوس'),(3,'ون و مینی بوس','minibus','minibus'),
(3,'نارون','نارون','نارون'),(3,'نارون','narvan','narvan'),(3,'نارون','xml6482','xml6482'),
(3,'نارون','xml6482e','xml6482e'),
(2,'ام وی ام','m v m','m v m'),
(2,'تندر ۹۰ / ال ۹۰','تندر90','تندر90'),
(2,'تندر ۹۰ / ال ۹۰','تندر 90','تندر 90'),
(2,'تندر ۹۰ / ال ۹۰','ال نود','ال نود');
