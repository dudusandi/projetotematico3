<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../model/notificacao.php';

class notificacaodao {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function criar(notificacao $notificacao) {
        $sql = "INSERT INTO notificacoes 
                (usuario_id_destino, usuario_id_origem, produto_id, tipo_notificacao, mensagem, link) 
                VALUES (:uid_destino, :uid_origem, :pid, :tipo, :msg, :link) RETURNING id, data_criacao";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':uid_destino', $notificacao->getUsuarioIdDestino(), PDO::PARAM_INT);
            $stmt->bindValue(':uid_origem', $notificacao->getUsuarioIdOrigem(), PDO::PARAM_INT);
            $stmt->bindValue(':pid', $notificacao->getProdutoId(), PDO::PARAM_INT);
            $stmt->bindValue(':tipo', $notificacao->getTiponotificacao(), PDO::PARAM_STR);
            $stmt->bindValue(':msg', $notificacao->getMensagem(), PDO::PARAM_STR);
            $stmt->bindValue(':link', $notificacao->getLink(), PDO::PARAM_STR);
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $notificacao->setId($result['id']);
                $notificacao->setDataCriacao($result['data_criacao']);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
            return false;
        }
    }

    public function buscarPorUsuarioIdDestino($usuario_id_destino, $apenasNaoLidas = false, $limite = 10, $offset = 0) {
        $sql = "SELECT * FROM notificacoes WHERE usuario_id_destino = :uid_destino";
        if ($apenasNaoLidas) {
            $sql .= " AND lida = FALSE";
        }
        $sql .= " ORDER BY data_criacao DESC LIMIT :limite OFFSET :offset";
        
        $notificacoes = [];
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':uid_destino', $usuario_id_destino, PDO::PARAM_INT);
            $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $notif = new notificacao(
                    $row['usuario_id_destino'],
                    $row['tipo_notificacao'],
                    $row['mensagem'],
                    $row['usuario_id_origem'],
                    $row['produto_id'],
                    $row['link']
                );
                $notif->setId($row['id']);
                $notif->setLida($row['lida']);
                $notif->setDataCriacao($row['data_criacao']);
                $notificacoes[] = $notif;
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar notificações: " . $e->getMessage());
        }
        return $notificacoes;
    }

    public function contarNaoLidas($usuario_id_destino) {
        $sql = "SELECT COUNT(*) FROM notificacoes WHERE usuario_id_destino = :uid_destino AND lida = FALSE";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':uid_destino', $usuario_id_destino, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erro ao contar notificações não lidas: " . $e->getMessage());
            return 0;
        }
    }

    public function marcarComoLida($notificacao_id, $usuario_id_destino) {
        $sql = "UPDATE notificacoes SET lida = TRUE WHERE id = :nid AND usuario_id_destino = :uid_destino";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':nid', $notificacao_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid_destino', $usuario_id_destino, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
            return false;
        }
    }

    public function marcarTodasComoLidas($usuario_id_destino) {
        $sql = "UPDATE notificacoes SET lida = TRUE WHERE usuario_id_destino = :uid_destino AND lida = FALSE";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':uid_destino', $usuario_id_destino, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao marcar todas as notificações como lidas: " . $e->getMessage());
            return false;
        }
    }
    
    public function buscarPorId($notificacao_id) {
        $sql = "SELECT * FROM notificacoes WHERE id = :nid";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':nid', $notificacao_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $notif = new notificacao(
                    $row['usuario_id_destino'],
                    $row['tipo_notificacao'],
                    $row['mensagem'],
                    $row['usuario_id_origem'],
                    $row['produto_id'],
                    $row['link']
                );
                $notif->setId($row['id']);
                $notif->setLida($row['lida']);
                $notif->setDataCriacao($row['data_criacao']);
                return $notif;
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar notificação por ID: " . $e->getMessage());
        }
        return null;
    }

    public function deletarnotificacao($notificacao_id, $usuario_id_destino) {
        $sql = "DELETE FROM notificacoes WHERE id = :nid AND usuario_id_destino = :uid_destino";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':nid', $notificacao_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid_destino', $usuario_id_destino, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erro ao deletar notificação: " . $e->getMessage());
            return false;
        }
    }
} 