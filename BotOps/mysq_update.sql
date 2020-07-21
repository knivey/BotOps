alter table Binds drop column onlyFrom;
alter table Binds drop column syntax;
alter table Binds drop column description;
ALTER TABLE Binds MODIFY func VARCHAR(255) ;
update Binds set func = CONCAT('cmd_',func);
