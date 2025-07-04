<?php
session_start();

require_once __DIR__ . '/../config/database.php'; 
require_once __DIR__ . '/../dao/mensagem_dao.php';
require_once __DIR__ . '/../model/mensagem.php';
require_once __DIR__ . '/../dao/cliente_dao.php'; 
require_once __DIR__ . '/../dao/notificacaodao.php'; 
require_once __DIR__ . '/../model/notificacao.php';  


function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}


function sendJsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    $responseData = [
        'success' => $success,
        'message' => $message
    ];
    if (!empty($data)) {
        $responseData = array_merge($responseData, $data);
    }
    echo json_encode($responseData);
    exit();
}

if (!isset($_SESSION['usuario_id'])) {
    if (isAjaxRequest()) {
        sendJsonResponse(false, 'Acesso não autorizado.');
    } else {
        http_response_code(403); 
        echo "Acesso não autorizado.";
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conteudo = trim($_POST['conteudo'] ?? '');
    $destinatario_id = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT);
    $remetente_id_form = filter_input(INPUT_POST, 'remetente_id', FILTER_VALIDATE_INT);

    $usuario_logado_id = (int)$_SESSION['usuario_id'];

    if (!$destinatario_id || !$remetente_id_form) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'ID do destinatário ou remetente inválido.');
        } else {
            $_SESSION['erro_mensagem'] = "ID do destinatário ou remetente inválido.";
            header('Location: ../view/minhas_mensagens.php');
            exit();
        }
    }

    if ($remetente_id_form !== $usuario_logado_id) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Tentativa de envio de mensagem por usuário não autorizado.');
        } else {
            $_SESSION['erro_mensagem'] = "Tentativa de envio de mensagem por usuário não autorizado.";
            header('Location: ../view/minhas_mensagens.php'); 
            exit();
        }
    }
    
    $clienteDAO = new ClienteDAO();
    if (!$clienteDAO->buscarPorId($destinatario_id)) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Destinatário não encontrado.');
        } else {
            $_SESSION['erro_mensagem'] = "Destinatário não encontrado.";
            header('Location: ../view/minhas_mensagens.php');
            exit();
        }
    }

    
    if (empty($conteudo)) {
        $mensagemDAO = new MensagemDAO();
        $conversaExistente = $mensagemDAO->buscarConversa($usuario_logado_id, $destinatario_id);

        if (empty($conversaExistente)) {
            
            $mensagemInicial = new Mensagem(null, $usuario_logado_id, $destinatario_id, "Início da conversa.");
            $mensagemDAO->enviarMensagem($mensagemInicial);
        }

        
        header('Location: ../view/chat.php?usuario_id=' . $destinatario_id);
        exit();
    }

    
    $mensagemDAO = new MensagemDAO();
    $conversaExistente = $mensagemDAO->buscarConversa($usuario_logado_id, $destinatario_id);

    if (empty($conversaExistente)) {
        error_log("Nenhuma conversa existente encontrada. Criando uma nova conversa entre $usuario_logado_id e $destinatario_id.");
        
        $conteudoMensagemInicial = "Início da conversa";
        $mensagem = new Mensagem(null, $usuario_logado_id, $destinatario_id, $conteudoMensagemInicial);

        if (!$mensagemDAO->enviarMensagem($mensagem)) {
            error_log("Erro ao iniciar a conversa entre $usuario_logado_id e $destinatario_id.");
            if (isAjaxRequest()) {
                sendJsonResponse(false, 'Erro ao iniciar a conversa.');
            } else {
                $_SESSION['erro_mensagem'] = "Erro ao iniciar a conversa.";
                header('Location: ../view/minhas_mensagens.php');
                exit();
            }
        }
    }

    
    $mensagem = new Mensagem();
    $mensagem->setRemetenteId($usuario_logado_id); 
    $mensagem->setDestinatarioId($destinatario_id);
    $mensagem->setConteudo($conteudo);

    $mensagem_id_enviada = $mensagemDAO->enviarMensagem($mensagem);

    if ($mensagem_id_enviada) {
        
        $nome_remetente = $_SESSION['usuario_nome'] ?? 'Alguém'; 
        $notificacaodao = new notificacaodao(Database::getConnection()); 
        $tipo_notificacao = 'nova_mensagem_chat';
        $mensagem_notif = htmlspecialchars($nome_remetente) . " enviou uma nova mensagem para você.";
        $link_notif = "../view/chat.php?usuario_id=" . $usuario_logado_id; 

        $novanotificacao = new notificacao(
            $destinatario_id,     
            $tipo_notificacao,
            $mensagem_notif,
            $usuario_logado_id,   
            null,                
            $link_notif
        );
        if (!$notificacaodao->criar($novanotificacao)) {
            error_log("Falha ao criar notificação para nova mensagem de chat. Destinatário: $destinatario_id, Remetente: $usuario_logado_id");
        }

        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!',
                'mensagem_id' => $mensagem_id_enviada
            ]);
            exit();
        } else {
            $_SESSION['sucesso_mensagem'] = "Mensagem enviada!";
            header('Location: ../view/chat.php?usuario_id=' . $destinatario_id);
            exit();
        }
    } else {
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao enviar a mensagem. Tente novamente.'
            ]);
            exit();
        } else {
            $_SESSION['erro_mensagem'] = "Erro ao enviar a mensagem. Tente novamente.";
            header('Location: ../view/chat.php?usuario_id=' . $destinatario_id);
            exit();
        }
    }

} else {
    if (isAjaxRequest()) {
        sendJsonResponse(false, 'Método não permitido.');
    } else {
        http_response_code(405); 
        echo "Método não permitido.";
        exit();
    }
}
