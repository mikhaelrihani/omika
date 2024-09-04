<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240904134708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, contact_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(50) NOT NULL, author VARCHAR(50) NOT NULL, reason VARCHAR(1000) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, planning_update TINYINT(1) NOT NULL, INDEX IDX_765AE0C9A76ED395 (user_id), INDEX IDX_765AE0C9E7A1254A (contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE business (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, business_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', firstname VARCHAR(255) NOT NULL, surname VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) NOT NULL, whatsapp VARCHAR(20) DEFAULT NULL, job VARCHAR(255) NOT NULL, late_count INT DEFAULT NULL, INDEX IDX_4C62E638A89DB457 (business_id), UNIQUE INDEX UNIQ_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dish (id INT AUTO_INCREMENT NOT NULL, picture_id INT NOT NULL, dish_category_id INT NOT NULL, recipe_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(100) NOT NULL, name_gender VARCHAR(5) NOT NULL, slug VARCHAR(50) NOT NULL, price NUMERIC(10, 2) NOT NULL, UNIQUE INDEX UNIQ_957D8CB8EE45BDBF (picture_id), INDEX IDX_957D8CB8C057AE07 (dish_category_id), UNIQUE INDEX UNIQ_957D8CB859D8A214 (recipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dish_category (id INT AUTO_INCREMENT NOT NULL, picture_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1FB098AAEE45BDBF (picture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dod (id INT AUTO_INCREMENT NOT NULL, menu_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, infos VARCHAR(255) DEFAULT NULL, order_day INT NOT NULL, INDEX IDX_182568C7CCD7E912 (menu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, event_section_id INT NOT NULL, event_frequence_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', side VARCHAR(255) NOT NULL, visible TINYINT(1) NOT NULL, status VARCHAR(255) NOT NULL, text VARCHAR(255) NOT NULL, author VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, periode_start DATE NOT NULL, periode_end DATE NOT NULL, periode_unlimited TINYINT(1) NOT NULL, INDEX IDX_3BAE0AA780CD3A55 (event_section_id), INDEX IDX_3BAE0AA7635B72F6 (event_frequence_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_frequence (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', everyday TINYINT(1) NOT NULL, week_days JSON NOT NULL, month_day INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_section (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(25) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ingredient (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, unit_id INT NOT NULL, recipe_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', quantity NUMERIC(10, 2) NOT NULL, INDEX IDX_6BAF78704584665A (product_id), INDEX IDX_6BAF7870F8BD700D (unit_id), INDEX IDX_6BAF787059D8A214 (recipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inventory (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, month VARCHAR(25) NOT NULL, author VARCHAR(255) NOT NULL, year INT NOT NULL, pdf_path VARCHAR(255) NOT NULL, excel_path VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inventory_room (inventory_id INT NOT NULL, room_id INT NOT NULL, INDEX IDX_38399EB59EEA759 (inventory_id), INDEX IDX_38399EB554177093 (room_id), PRIMARY KEY(inventory_id, room_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE issue (id INT AUTO_INCREMENT NOT NULL, technician_contacted_id INT NOT NULL, technician_coming_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', count_number INT NOT NULL, status VARCHAR(255) NOT NULL, author VARCHAR(255) NOT NULL, summary VARCHAR(50) NOT NULL, description VARCHAR(1000) NOT NULL, fix_date DATE DEFAULT NULL, fix_time TIME DEFAULT NULL, follow_up INT DEFAULT NULL, solution VARCHAR(1000) DEFAULT NULL, UNIQUE INDEX UNIQ_12AD233ED5AFAE6D (technician_contacted_id), UNIQUE INDEX UNIQ_12AD233E5E1744C5 (technician_coming_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kitchen_space (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', week INT NOT NULL, author VARCHAR(100) NOT NULL, fish_grill VARCHAR(50) DEFAULT NULL, meat_grill VARCHAR(50) DEFAULT NULL, chef_special VARCHAR(100) DEFAULT NULL, special VARCHAR(100) DEFAULT NULL, status VARCHAR(100) NOT NULL, pdf_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, writer_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', recipient_id INT NOT NULL, recipient_type VARCHAR(255) NOT NULL, text VARCHAR(1000) NOT NULL, INDEX IDX_B6BD307F1BC7E6B6 (writer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mime (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', text VARCHAR(1000) NOT NULL, UNIQUE INDEX UNIQ_CFBDFA14A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, supplier_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivery_date DATE NOT NULL, author VARCHAR(255) NOT NULL, sending_method VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, note VARCHAR(255) NOT NULL, pdf_path VARCHAR(255) NOT NULL, INDEX IDX_F52993982ADD6D8C (supplier_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE picture (id INT AUTO_INCREMENT NOT NULL, mime_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', slug VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, path VARCHAR(255) NOT NULL, INDEX IDX_16DB4F89ACAC0426 (mime_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, unit_id INT NOT NULL, supplier_id INT NOT NULL, type_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', kitchen_name VARCHAR(50) NOT NULL, commercial_name VARCHAR(255) NOT NULL, slug VARCHAR(50) NOT NULL, price NUMERIC(10, 2) NOT NULL, conditionning VARCHAR(255) NOT NULL, supplier_favorite TINYINT(1) NOT NULL, INDEX IDX_D34A04ADF8BD700D (unit_id), INDEX IDX_D34A04AD2ADD6D8C (supplier_id), INDEX IDX_D34A04ADC54C8C93 (type_id), INDEX kitchen_name_idx (kitchen_name), INDEX commercial_name_idx (commercial_name), INDEX kitchen_commercial_name_idx (kitchen_name, commercial_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_inventory (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, inventory_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', quantity_big NUMERIC(10, 2) NOT NULL, quantity_small NUMERIC(10, 2) NOT NULL, INDEX IDX_DF8DFCBB4584665A (product_id), INDEX IDX_DF8DFCBB9EEA759 (inventory_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_order (id INT AUTO_INCREMENT NOT NULL, orders_id INT NOT NULL, product_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', quantity NUMERIC(10, 2) NOT NULL, INDEX IDX_5475E8C4CFFE9AD6 (orders_id), INDEX IDX_5475E8C44584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_type (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recipe (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recipe_product (recipe_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_9FAE0AED59D8A214 (recipe_id), INDEX IDX_9FAE0AED4584665A (product_id), PRIMARY KEY(recipe_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recipe_advise (id INT AUTO_INCREMENT NOT NULL, recipe_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', order_advise INT NOT NULL, description VARCHAR(1000) NOT NULL, INDEX IDX_A35E02FD59D8A214 (recipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recipe_step (id INT AUTO_INCREMENT NOT NULL, recipe_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', description VARCHAR(1000) NOT NULL, order_step INT NOT NULL, INDEX IDX_3CA2A4E359D8A214 (recipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE room (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, location_details VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE room_product (id INT AUTO_INCREMENT NOT NULL, room_id INT NOT NULL, product_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', room_shelf INT NOT NULL, INDEX IDX_3F68B84D54177093 (room_id), INDEX IDX_3F68B84D4584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rupture (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', info VARCHAR(1000) NOT NULL, origin VARCHAR(50) NOT NULL, unique_solution VARCHAR(255) DEFAULT NULL, solution VARCHAR(1000) DEFAULT NULL, status VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_D21071124584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE supplier (id INT AUTO_INCREMENT NOT NULL, business_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', logistic VARCHAR(1000) NOT NULL, habits VARCHAR(1000) DEFAULT NULL, order_days JSON NOT NULL, good_to_know VARCHAR(1000) DEFAULT NULL, delivery_days JSON NOT NULL, UNIQUE INDEX UNIQ_9B2A6C7EA89DB457 (business_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE template (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, text VARCHAR(1000) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE unit (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(50) NOT NULL, symbol VARCHAR(5) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, business_id INT NOT NULL, user_login_id INT NOT NULL, avatar_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', firstname VARCHAR(255) NOT NULL, surname VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, whatsapp VARCHAR(20) DEFAULT NULL, job VARCHAR(255) DEFAULT NULL, late_count INT NOT NULL, pseudo VARCHAR(50) NOT NULL, private_note VARCHAR(1000) NOT NULL, INDEX IDX_8D93D649A89DB457 (business_id), UNIQUE INDEX UNIQ_8D93D649BC3F045D (user_login_id), UNIQUE INDEX UNIQ_8D93D64986383B10 (avatar_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_login (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E638A89DB457 FOREIGN KEY (business_id) REFERENCES business (id)');
        $this->addSql('ALTER TABLE dish ADD CONSTRAINT FK_957D8CB8EE45BDBF FOREIGN KEY (picture_id) REFERENCES picture (id)');
        $this->addSql('ALTER TABLE dish ADD CONSTRAINT FK_957D8CB8C057AE07 FOREIGN KEY (dish_category_id) REFERENCES dish_category (id)');
        $this->addSql('ALTER TABLE dish ADD CONSTRAINT FK_957D8CB859D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id)');
        $this->addSql('ALTER TABLE dish_category ADD CONSTRAINT FK_1FB098AAEE45BDBF FOREIGN KEY (picture_id) REFERENCES picture (id)');
        $this->addSql('ALTER TABLE dod ADD CONSTRAINT FK_182568C7CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA780CD3A55 FOREIGN KEY (event_section_id) REFERENCES event_section (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7635B72F6 FOREIGN KEY (event_frequence_id) REFERENCES event_frequence (id)');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF78704584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF7870F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF787059D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id)');
        $this->addSql('ALTER TABLE inventory_room ADD CONSTRAINT FK_38399EB59EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_room ADD CONSTRAINT FK_38399EB554177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233ED5AFAE6D FOREIGN KEY (technician_contacted_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233E5E1744C5 FOREIGN KEY (technician_coming_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F1BC7E6B6 FOREIGN KEY (writer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993982ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
        $this->addSql('ALTER TABLE picture ADD CONSTRAINT FK_16DB4F89ACAC0426 FOREIGN KEY (mime_id) REFERENCES mime (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADF8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADC54C8C93 FOREIGN KEY (type_id) REFERENCES product_type (id)');
        $this->addSql('ALTER TABLE product_inventory ADD CONSTRAINT FK_DF8DFCBB4584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE product_inventory ADD CONSTRAINT FK_DF8DFCBB9EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id)');
        $this->addSql('ALTER TABLE product_order ADD CONSTRAINT FK_5475E8C4CFFE9AD6 FOREIGN KEY (orders_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE product_order ADD CONSTRAINT FK_5475E8C44584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE recipe_product ADD CONSTRAINT FK_9FAE0AED59D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_product ADD CONSTRAINT FK_9FAE0AED4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_advise ADD CONSTRAINT FK_A35E02FD59D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id)');
        $this->addSql('ALTER TABLE recipe_step ADD CONSTRAINT FK_3CA2A4E359D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id)');
        $this->addSql('ALTER TABLE room_product ADD CONSTRAINT FK_3F68B84D54177093 FOREIGN KEY (room_id) REFERENCES room (id)');
        $this->addSql('ALTER TABLE room_product ADD CONSTRAINT FK_3F68B84D4584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE rupture ADD CONSTRAINT FK_D21071124584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE supplier ADD CONSTRAINT FK_9B2A6C7EA89DB457 FOREIGN KEY (business_id) REFERENCES business (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A89DB457 FOREIGN KEY (business_id) REFERENCES business (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649BC3F045D FOREIGN KEY (user_login_id) REFERENCES user_login (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64986383B10 FOREIGN KEY (avatar_id) REFERENCES picture (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9A76ED395');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9E7A1254A');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E638A89DB457');
        $this->addSql('ALTER TABLE dish DROP FOREIGN KEY FK_957D8CB8EE45BDBF');
        $this->addSql('ALTER TABLE dish DROP FOREIGN KEY FK_957D8CB8C057AE07');
        $this->addSql('ALTER TABLE dish DROP FOREIGN KEY FK_957D8CB859D8A214');
        $this->addSql('ALTER TABLE dish_category DROP FOREIGN KEY FK_1FB098AAEE45BDBF');
        $this->addSql('ALTER TABLE dod DROP FOREIGN KEY FK_182568C7CCD7E912');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA780CD3A55');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7635B72F6');
        $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_6BAF78704584665A');
        $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_6BAF7870F8BD700D');
        $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_6BAF787059D8A214');
        $this->addSql('ALTER TABLE inventory_room DROP FOREIGN KEY FK_38399EB59EEA759');
        $this->addSql('ALTER TABLE inventory_room DROP FOREIGN KEY FK_38399EB554177093');
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233ED5AFAE6D');
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233E5E1744C5');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F1BC7E6B6');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14A76ED395');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993982ADD6D8C');
        $this->addSql('ALTER TABLE picture DROP FOREIGN KEY FK_16DB4F89ACAC0426');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADF8BD700D');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD2ADD6D8C');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADC54C8C93');
        $this->addSql('ALTER TABLE product_inventory DROP FOREIGN KEY FK_DF8DFCBB4584665A');
        $this->addSql('ALTER TABLE product_inventory DROP FOREIGN KEY FK_DF8DFCBB9EEA759');
        $this->addSql('ALTER TABLE product_order DROP FOREIGN KEY FK_5475E8C4CFFE9AD6');
        $this->addSql('ALTER TABLE product_order DROP FOREIGN KEY FK_5475E8C44584665A');
        $this->addSql('ALTER TABLE recipe_product DROP FOREIGN KEY FK_9FAE0AED59D8A214');
        $this->addSql('ALTER TABLE recipe_product DROP FOREIGN KEY FK_9FAE0AED4584665A');
        $this->addSql('ALTER TABLE recipe_advise DROP FOREIGN KEY FK_A35E02FD59D8A214');
        $this->addSql('ALTER TABLE recipe_step DROP FOREIGN KEY FK_3CA2A4E359D8A214');
        $this->addSql('ALTER TABLE room_product DROP FOREIGN KEY FK_3F68B84D54177093');
        $this->addSql('ALTER TABLE room_product DROP FOREIGN KEY FK_3F68B84D4584665A');
        $this->addSql('ALTER TABLE rupture DROP FOREIGN KEY FK_D21071124584665A');
        $this->addSql('ALTER TABLE supplier DROP FOREIGN KEY FK_9B2A6C7EA89DB457');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649A89DB457');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649BC3F045D');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64986383B10');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE business');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE dish');
        $this->addSql('DROP TABLE dish_category');
        $this->addSql('DROP TABLE dod');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_frequence');
        $this->addSql('DROP TABLE event_section');
        $this->addSql('DROP TABLE ingredient');
        $this->addSql('DROP TABLE inventory');
        $this->addSql('DROP TABLE inventory_room');
        $this->addSql('DROP TABLE issue');
        $this->addSql('DROP TABLE kitchen_space');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE mime');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE picture');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_inventory');
        $this->addSql('DROP TABLE product_order');
        $this->addSql('DROP TABLE product_type');
        $this->addSql('DROP TABLE recipe');
        $this->addSql('DROP TABLE recipe_product');
        $this->addSql('DROP TABLE recipe_advise');
        $this->addSql('DROP TABLE recipe_step');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE room_product');
        $this->addSql('DROP TABLE rupture');
        $this->addSql('DROP TABLE supplier');
        $this->addSql('DROP TABLE template');
        $this->addSql('DROP TABLE unit');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_login');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
