### Requetes pour test:

## tag
#  liste de tags avec les informations associées sur les utilisateurs, leurs tâches et les informations non lues
- SELECT tag.id AS tag_id, tag.day, tag_info.user_id AS info_user_id, tag_info.unread_info_count, tag_task.user_id AS task_user_id, tag_task.tag_count FROM tag LEFT JOIN ( SELECT DISTINCT tag_id, user_id, unread_info_count FROM tag_info ) AS tag_info ON tag.id = tag_info.tag_id LEFT JOIN ( SELECT DISTINCT tag_id, user_id, tag_count FROM tag_task ) AS tag_task ON tag.id = tag_task.tag_id ORDER BY `tag_id` ASC;

# 