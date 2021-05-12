# Log de Prefixo Delegado com Mikrotik via RADIUS

## Descrição

* IMPORTANTE: durante o processo está sendo utilizado o editor de textos NANO, por questões de praticidade para usuários não tão acostumados com Linux. Ajuste conforme achar necessário.

Projeto simples para instalar/configurar servidor RADIUS para receber os logs (accounting) de prefixos delegados para clientes, muito útil para provedores que necessitem manter logs de acesso em razão do Marco Civil.

O projeto foi testado/homologado utilizando:

* Debian 10 x64
* Mikrotik ROS 6.48 (versão mínima necessária para envio do PD)

Em caso de correções, dúvidas ou sugestões, fiquem a vontade para entrar em contato via email ou telegram

Email: contato@l1consultoria.com.br
Telegram: @wilsonritt

## Pré-requisitos

1. Atualizar repositórios e instalar pacotes que serão usados
  ```sh
  apt update && apt install git -y
  ```
2. Instalar e configurar o Apache
  * Instalação:
     ```sh
     apt install apache2 apache2-utils -y
     ```
  * Habilita Mod ReWrite
     ```sh
     a2enmod rewrite
     ```
  * Configurar o VirtualHost do Apache (editar conforme cenário)
     ```sh
     tee /etc/apache2/sites-enabled/000-default.conf > /dev/null <<EOF
     <VirtualHost *:80>
           ServerName xxx.domain.com.br
           ServerAdmin wilson.ritt@l1consultoria.com.br
           DocumentRoot /var/www/html

           <Directory /var/www/html>
                   Options -Indexes +FollowSymLinks -MultiViews
                   AllowOverride All
                   Order allow,deny
                   allow from all
           </Directory>

           ErrorLog \${APACHE_LOG_DIR}/error.log
           CustomLog \${APACHE_LOG_DIR}/access.log combined
     </VirtualHost>

     # vim: syntax=apache ts=4 sw=4 sts=4 sr noet
     EOF
     ```
  * Desabilita assinaturas, tokens e remove o index.html padrão por razões de segurança
     ```sh
     sed -i 's/ServerTokens OS/ServerTokens Prod/g' /etc/apache2/conf-available/security.conf
     sed -i 's/ServerSignature On/ServerSignature Off/g' /etc/apache2/conf-available/security.conf
     rm /var/www/html/index.html
     ```
  * Reinicie o serviço
     ```sh
     systemctl restart apache2.service
     ```

3. PHP
  ```sh
  apt install libapache2-mod-php php php-mysql php-cli php-mbstring php-curl -y && systemctl restart apache2.service
  ```

4. MariaDB
  * Instalação
     ```sh
     apt install mariadb-server mariadb-client -y
     ```
  * Configuração (configure uma senha de root e padrões de segurança)
     ```sh
     mysql_secure_installation
     ```

5. FreeRadius
  ```sh
  apt install freeradius-mysql -y
  ```

## Configuração

### Servidor RADIUS
1. Crie o banco de dados para o RADIUS
   * Abre o console do MySQL
      ```sh
      mysql
      ```
   * Cria o Banco de Dados
      ```MYSQL
      CREATE DATABASE radius;
      \q
      ```
2. Importe o SCHEMA do FreeRadius para o MySQL
   ```sh
   mysql radius < /etc/freeradius/3.0/mods-config/sql/main/mysql/schema.sql
   ```
3. Altere a senha para o usuário do RADIUS no arquivo de configuração do MySQL do FreeRadius e importe
   * Abre o arquivo para edição
      ```sh
      nano /etc/freeradius/3.0/mods-config/sql/main/mysql/setup.sql
      ```
   * Edite a seguinte linha
      ```MYSQL
      SET PASSWORD FOR 'radius'@'localhost' = PASSWORD('SUA_SENHA_AQUI');
      ```
   * Importe o arquivo para o Banco de Dados
      ```sh
      mysql radius < /etc/freeradius/3.0/mods-config/sql/main/mysql/setup.sql
      ```
4. Configurar as tabelas do RADIUS para suportarem os parâmetros (estou incluindo o "framedipv6address" para futura implementação, por enquanto ele só é repassado pelo accounting do PPP)
   * Abre o console do MySQL
      ```sh
      mysql
      ```
   * Ajusta tabelas para suportarem os campos que serão usados
      ```MYSQL
      \u radius
      ALTER TABLE radacct ADD framedipv6address VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci;
      ALTER TABLE radacct ADD delegatedipv6prefix VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci;
      ALTER TABLE radacct ADD mikrotikrealm VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci;
      \q
      ```
5. Habilite o módulo SQL no FreeRadius
   ```sh
   cd /etc/freeradius/3.0/mods-enabled/
   ln -s ../mods-available/sql sql
   ```
6. Configure suporte a SQL no site padrão do RADIUS
   * Abre o arquivo para edição
      ```sh
      nano /etc/freeradius/3.0/sites-available/default
      ```
   * Procure pelas linhas que contenham "sql-" ou "#sql", e deixe-as apenas como "sql". Exemplo de arquivo editado:
      ```sh
      ### OS 3 ASTERISCOS SÃO APENAS PARA DESTACAR, NÃO INCLUA OS '***' ###
      (...)
       authorize {
               filter_username
               preprocess
               chap
               mschap
               digest
               suffix
               eap {
                       ok = return
               }
               files
               ***sql***
               -ldap
               expiration
               logintime
               pap
       }
       (...)
       accounting {
               detail
               unix
               ***sql***
               exec
               attr_filter.accounting_response
       }
       session {
       }
       post-auth {
               update {
                       &reply: += &session-state:
               }
               sql
               exec
               remove_reply_message_if_eap
            Post-Auth-Type REJECT {
                    ***sql***
                    attr_filter.access_reject
                    eap
                    remove_reply_message_if_eap
            }
            Post-Auth-Type Challenge {
            }
       }
      (...)
      ```

7. Altere a opção "safe_characters", "accounting { column_list, type { start { query VALUES } } }"
   * Abre o arquivo para edição
      ```sh
      nano /etc/freeradius/3.0/mods-config/sql/main/mysql/queries.conf
      ```
   * Adicione os caracteres usados pelo Mikrotik para PPPoE (< e >)
      ```sh
      #(...)
      safe_characters = "@abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-_: /<>"
      ```
   * Adicionar os parâmetros em "column_list" e "start" > "values" (muita atenção nesta parte, respeitar os espaçamentos e sinais (\ " ,)
      ```sh
      #(...)
      accounting {
          reference = "%{tolower:type.%{Acct-Status-Type}.query}"

          # Write SQL queries to a logfile. This is potentially useful for bulk inserts
          # when used with the rlm_sql_null driver.
          # logfile = ${logdir}/accounting.sql

          column_list = "\
                  acctsessionid,          acctuniqueid,           username, \
                  realm,                  nasipaddress,           nasportid, \
                  nasporttype,            acctstarttime,          acctupdatetime, \
                  acctstoptime,           acctsessiontime,        acctauthentic, \
                  connectinfo_start,      connectinfo_stop,       acctinputoctets, \
                  acctoutputoctets,       calledstationid,        callingstationid, \
                  acctterminatecause,     servicetype,            framedprotocol, \
                  framedipaddress,        framedipv6address,      delegatedipv6prefix, \
                  mikrotikrealm"
      #(...)
      start {
      #
      #  Insert a new record into the sessions table
      #
      query = "\
              INSERT INTO ${....acct_table1} \
                      (${...column_list}) \
              VALUES \
                      ('%{Acct-Session-Id}', \
                      '%{Acct-Unique-Session-Id}', \
                      '%{SQL-User-Name}', \
                      '%{Realm}', \
                      '%{NAS-IP-Address}', \
                      '%{%{NAS-Port-ID}:-%{NAS-Port}}', \
                      '%{NAS-Port-Type}', \
                      FROM_UNIXTIME(%{integer:Event-Timestamp}), \
                      FROM_UNIXTIME(%{integer:Event-Timestamp}), \
                      NULL, \
                      '0', \
                      '%{Acct-Authentic}', \
                      '%{Connect-Info}', \
                      '', \
                      '0', \
                      '0', \
                      '%{Called-Station-Id}', \
                      '%{Calling-Station-Id}', \
                      '', \
                      '%{Service-Type}', \
                      '%{Framed-Protocol}', \
                      '%{Framed-IP-Address}', \
                      '%{Framed-IPv6-Prefix}', \
                      '%{Delegated-IPv6-Prefix}', \
                      '%{Mikrotik-Realm}')"
      ```
8. Configure o Driver SQL (driver = "rlm_sql_null"), dialect, "Connection info" e "read_clients" (descomente e configure como YES)
   * Abre o arquivo para edição
      ```sh
      nano /etc/freeradius/3.0/mods-enabled/sql
      ```
   * Exemplo de arquivo já editado
      ```sh
      ### OS 3 ASTERISCOS SÃO APENAS PARA DESTACAR, NÃO INCLUA OS '***' ###
      sql {
          ***driver = "rlm_sql_mysql"***
          ***dialect = "mysql"***
          ***server = "localhost"***
          ***port = 3306***
          ***login = "radius"***
          ***password = "SUA_SENHA_AQUI"***
          ***radius_db = "radius"***
          acct_table1 = "radacct"
          acct_table2 = "radacct"
          postauth_table = "radpostauth"
          authcheck_table = "radcheck"
          groupcheck_table = "radgroupcheck"
          authreply_table = "radreply"
          groupreply_table = "radgroupreply"
          usergroup_table = "radusergroup"
          delete_stale_sessions = yes
          pool {
                  start = ${thread[pool].start_servers}
                  min = ${thread[pool].min_spare_servers}
                  max = ${thread[pool].max_servers}
                  spare = ${thread[pool].max_spare_servers}
                  uses = 0
                  retry_delay = 30
                  lifetime = 0
                  idle_timeout = 60
          }
          ***read_clients = yes***
          client_table = "nas"
          group_attribute = "SQL-Group"
          $INCLUDE ${modconfdir}/${.:name}/main/${dialect}/queries.conf
      }
      ```

9. Crie os NAS (RBs que vão enviar informações para o servidor RADIUS) no MySQL
   * Abre o console do MySQL
      ```sh
      mysql
      ```
   * Insere os NAS na tabela (repetir para cada concentrador que for permitir enviar os logs)
      ```MYSQL
      \u radius
      INSERT INTO nas (nasname,shortname,type,secret,community,description)
      VALUES ('IP_DO_CONCENTRADOR','NOME_DO_CONCENTRADOR','mikrotik','SENHA_SEGURA_RADIUS','','NOME_DO_CONCENTRADOR');
      \q
   ```
   * Reinicie o serviço do FreeRadius
      ```sh
      service freeradius restart
      ```
### Mikrotik

No concentrador, basta adicionar o servidor RADIUS para envio conforme parametrização abaixo:

```console
/radius
add address=IP_DO_SERVIDOR_RADIUS realm=NOME_DO_POP secret=SENHA_SEGURA_RADIUS service=dhcp src-address=IP_DO_CONCENTRADOR
```

### Servidor WEB

Para fazer o deploy da página WEB para consultas:

```sh
cd /tmp
git clone https://github.com/wilsonritt/mikrotik-pd-ipv6-radius
mv mikrotik-pd-ipv6-radius/logv6 /var/www/html/
```

Após copiar o site, editar os arquivos de configuração:

```sh
cd /var/www/html/logv6/conf/
nano config.php
```

```php
<?php
    define('INCLUDE_PATH','http://SEU.DOMINIO.AQUI/logv6/');   // Endereço onde vai ficar o site
    define('COMPANY_NAME','L1 CONSULTORIA');                   // Nome de sua empresa
    define('DB_HOST','[2001:db8::bebe:cafe]');                 // IP do MySQL do RADIUS
    define('DB_USER','radius');                                // Usuário do radius
    define('DB_PASS','PASSWORD');                              // Senha criada no passo '3' da configuração do RADIUS
    define('DB','radius');                                     // Banco de dados do radius
    define('DB_PORT','3306');                                  // Porta do MySQL
?>
```
Editar o arquivo .htaccess informando os IPs que podem acessar o servidor (NÃO SE ESQUEÇAM DESSA PARTE!)

```sh
nano /var/www/html/logv6/.htaccess
```
```console
<RequireAny>
 Require ip 127.0.0.1
 Require ip ::1
</RequireAny>

```

## O que ainda falta:
* Criar página de login (controle feito por htaccess por enquanto)
* Criar função de cadastro de NAS pela interface

