<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kling AI - Генератор изображений и видео</title>
    <style>
        /* Сброс стандартных отступов и базовые настройки */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Подключение шрифта Inter */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        body {
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #e2e8f0;
        }

        /* Основной стилизованный блок в стиле Dark Web 2.0 */
        .container {
            background: rgba(30, 32, 48, 0.9);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            max-width: 700px;
            width: 100%;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(102, 126, 234, 0.1) inset;
            border: 1px solid rgba(102, 126, 234, 0.15);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Эффект при наведении на блок */
        .container:hover {
            transform: translateY(-8px);
            box-shadow: 
                0 35px 70px rgba(0, 0, 0, 0.7),
                0 0 0 1px rgba(102, 126, 234, 0.2) inset;
        }

        /* Заголовок с неоновым эффектом */
        h1 {
            background: linear-gradient(90deg, #667eea, #9f7aea, #667eea);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 2em; /* Уменьшено на 33% */
            margin-bottom: 15px;
            text-align: center;
            font-weight: 800;
            letter-spacing: -0.3px;
            animation: gradient 3s ease infinite;
        }

        @keyframes gradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Основной абзац с описанием */
        .lead {
            font-size: 0.975em; /* Уменьшено на 35% */
            color: #a0aec0;
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.5;
            padding: 0 10px;
            font-weight: 400;
        }

        /* Стили для карточек-преимуществ */
        .feature {
            background: linear-gradient(145deg, rgba(45, 49, 73, 0.8), rgba(30, 34, 56, 0.9));
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .feature::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .feature:hover::before {
            left: 100%;
        }

        .feature:hover {
            background: linear-gradient(145deg, rgba(55, 59, 83, 0.9), rgba(40, 44, 66, 0.95));
            transform: translateX(8px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.25);
            border-left-color: #9f7aea;
        }

        /* Заголовки внутри карточек */
        .feature strong {
            color: #e2e8f0;
            font-size: 1.05em; /* Уменьшено на 25% */
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        /* Текст внутри карточек */
        .feature p {
            color: #a0aec0;
            line-height: 1.6;
            font-size: 0.9em; /* Уменьшено на 18% */
            font-weight: 400;
        }

        /* Стили для иконок-эмодзи */
        .emoji {
            font-size: 1.2em; /* Уменьшено на 20% */
            margin-right: 8px;
            vertical-align: middle;
        }

        /* Ссылка на Telegram бота */
        .telegram-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0088cc, #006699);
            color: white;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 0.975em; /* Уменьшено на 25% */
            font-weight: 600;
            margin: 30px auto 0;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 136, 204, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: fit-content;
            position: relative;
            overflow: hidden;
        }

        .telegram-link::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.5s ease;
        }

        .telegram-link:hover::after {
            transform: rotate(45deg) translate(50%, 50%);
        }

        .telegram-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 136, 204, 0.6);
            background: linear-gradient(135deg, #0099e6, #0088cc);
        }

        /* Центрирование ссылки */
        .link-container {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        /* Дополнительный декоративный элемент */
        .glow-effect {
            position: fixed;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.15) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: -1;
            filter: blur(40px);
        }

        /* Адаптивность для мобильных устройств */
        @media (max-width: 768px) {
            .container {
                padding: 25px 15px;
                margin: 10px;
            }
            
            h1 {
                font-size: 1.7em;
            }
            
            .lead {
                font-size: 0.9em;
            }
            
            .feature {
                padding: 16px;
            }
            
            .telegram-link {
                padding: 12px 22px;
                font-size: 0.9em;
            }
        }

        @media (max-width: 480px) {
            .container {
                border-radius: 16px;
                padding: 20px 12px;
            }
            
            h1 {
                font-size: 1.5em;
            }
            
            .feature {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="glow-effect"></div>
    
    <div class="container">
        <h1>Image to video</h1>
        
        <p class="lead">🤖 Генератор изображений и видео Kling AI. Самый быстрый и дешевый результат!</p>
        
        <div class="feature">
            <strong><span class="emoji">🎨</span>Генерация изображений</strong>
            <p>Превращаю ваши идеи в уникальные картины с помощью Midjourney. Современные нейросети создают изображения любого стиля по вашему описанию.</p>
        </div>
        
        <div class="feature">
            <strong><span class="emoji">🎬</span>Создание видео по фото</strong>
            <p>Загрузите свою фотографию → опишите действие → получайте анимированное видео через Kling AI! Профессиональная анимация за считанные минуты.</p>
        </div>
        
        <div class="feature">
            <strong><span class="emoji">⚡️</span>Быстро и удобно</strong>
            <p>Русский интерфейс, мгновенная обработка, поддержка всех популярных AI-моделей в одном боте. Работает прямо в Telegram без сложных установок.</p>
        </div>
        
        <div class="link-container">
            <a href="https://t.me/photo2live_bot" class="telegram-link" target="_blank">
                @photo2live_bot в Telegram
            </a>
        </div>
    </div>
</body>
</html>