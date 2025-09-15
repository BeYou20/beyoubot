# استخدم صورة PHP الرسمية مع Apache
FROM php:8.2-apache

# حدد مجلد العمل
WORKDIR /var/www/html

# انسخ كل ملفات المشروع إلى الحاوية
COPY . /var/www/html/

# تفعيل mod_rewrite لو تحتاجه (اختياري)
RUN a2enmod rewrite

# فتح البورت 10000 لـ Render
EXPOSE 10000

# تشغيل Apache في foreground
CMD ["apache2-foreground"]
