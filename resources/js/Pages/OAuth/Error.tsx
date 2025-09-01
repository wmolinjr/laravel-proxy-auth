import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Home, RefreshCw } from 'lucide-react';

interface ErrorProps {
  error: string;
  error_description: string;
  status: number;
}

const errorMessages = {
  invalid_request: {
    title: 'Solicitação Inválida',
    description: 'A solicitação OAuth contém parâmetros inválidos ou malformados.',
    icon: AlertCircle,
    color: 'text-orange-600',
  },
  unauthorized_client: {
    title: 'Cliente Não Autorizado',
    description: 'Este cliente não tem permissão para usar este fluxo de autorização.',
    icon: AlertCircle,
    color: 'text-red-600',
  },
  access_denied: {
    title: 'Acesso Negado',
    description: 'Você negou o acesso à aplicação.',
    icon: AlertCircle,
    color: 'text-orange-600',
  },
  unsupported_response_type: {
    title: 'Tipo de Resposta Não Suportado',
    description: 'O servidor de autorização não suporta este tipo de resposta.',
    icon: AlertCircle,
    color: 'text-red-600',
  },
  invalid_scope: {
    title: 'Escopo Inválido',
    description: 'O escopo solicitado é inválido, desconhecido ou malformado.',
    icon: AlertCircle,
    color: 'text-orange-600',
  },
  server_error: {
    title: 'Erro do Servidor',
    description: 'O servidor encontrou um erro inesperado.',
    icon: AlertCircle,
    color: 'text-red-600',
  },
  temporarily_unavailable: {
    title: 'Temporariamente Indisponível',
    description: 'O serviço está temporariamente indisponível.',
    icon: RefreshCw,
    color: 'text-yellow-600',
  },
  invalid_client: {
    title: 'Cliente Inválido',
    description: 'Autenticação do cliente falhou.',
    icon: AlertCircle,
    color: 'text-red-600',
  },
  invalid_grant: {
    title: 'Concessão Inválida',
    description: 'A concessão de autorização fornecida é inválida, expirou ou foi revogada.',
    icon: AlertCircle,
    color: 'text-red-600',
  },
  unsupported_grant_type: {
    title: 'Tipo de Concessão Não Suportado',
    description: 'O tipo de concessão de autorização não é suportado pelo servidor.',
    icon: AlertCircle,
    color: 'text-red-600',
  },
};

export default function Error({ error, error_description, status }: ErrorProps) {
  const errorInfo = errorMessages[error as keyof typeof errorMessages] || {
    title: 'Erro Desconhecido',
    description: 'Ocorreu um erro não identificado.',
    icon: AlertCircle,
    color: 'text-red-600',
  };

  const Icon = errorInfo.icon;

  const handleGoHome = () => {
    window.location.href = '/';
  };

  const handleRefresh = () => {
    window.location.reload();
  };

  return (
    <>
      <Head title={`Erro OAuth - ${errorInfo.title}`} />
      
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 p-4">
        <div className="w-full max-w-md space-y-6">
          {/* Header */}
          <div className="text-center">
            <div className={`mx-auto w-16 h-16 ${errorInfo.color} bg-white dark:bg-slate-800 rounded-full flex items-center justify-center mb-4 shadow-lg`}>
              <Icon className="w-8 h-8" />
            </div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-2">
              WMJ Identity Provider
            </h1>
          </div>

          <Card className="shadow-lg border-0">
            <CardHeader className="text-center">
              <CardTitle className={`text-xl ${errorInfo.color}`}>
                {errorInfo.title}
              </CardTitle>
              <CardDescription className="text-base">
                Código de erro: {status} - {error}
              </CardDescription>
            </CardHeader>
            
            <CardContent className="space-y-6">
              {/* Descrição do erro */}
              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription className="text-sm">
                  <strong>Descrição:</strong> {errorInfo.description}
                </AlertDescription>
              </Alert>

              {/* Descrição técnica se disponível */}
              {error_description && error_description !== errorInfo.description && (
                <Alert>
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription className="text-sm">
                    <strong>Detalhes técnicos:</strong> {error_description}
                  </AlertDescription>
                </Alert>
              )}

              {/* Dicas de resolução */}
              <div className="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg">
                <h3 className="font-semibold mb-2 text-slate-900 dark:text-slate-100">
                  Como resolver:
                </h3>
                <ul className="text-sm text-slate-600 dark:text-slate-400 space-y-1">
                  {error === 'access_denied' ? (
                    <>
                      <li>• Você escolheu não autorizar o acesso</li>
                      <li>• Tente novamente se mudou de ideia</li>
                      <li>• Entre em contato com o suporte se necessário</li>
                    </>
                  ) : error === 'invalid_client' ? (
                    <>
                      <li>• Verifique se a aplicação está configurada corretamente</li>
                      <li>• Entre em contato com o desenvolvedor da aplicação</li>
                      <li>• Certifique-se de que o cliente está ativo</li>
                    </>
                  ) : error === 'server_error' ? (
                    <>
                      <li>• Tente novamente em alguns minutos</li>
                      <li>• O problema pode ser temporário</li>
                      <li>• Entre em contato com o suporte se persistir</li>
                    </>
                  ) : (
                    <>
                      <li>• Verifique se a URL está correta</li>
                      <li>• Tente recarregar a página</li>
                      <li>• Entre em contato com o suporte se necessário</li>
                    </>
                  )}
                </ul>
              </div>

              {/* Informações técnicas */}
              <details className="bg-slate-100 dark:bg-slate-700 p-4 rounded-lg">
                <summary className="cursor-pointer font-semibold text-sm text-slate-700 dark:text-slate-300">
                  Informações Técnicas
                </summary>
                <div className="mt-3 text-xs font-mono space-y-1">
                  <p><span className="font-semibold">Error:</span> {error}</p>
                  <p><span className="font-semibold">Status:</span> {status}</p>
                  <p><span className="font-semibold">Timestamp:</span> {new Date().toISOString()}</p>
                  <p><span className="font-semibold">User Agent:</span> {navigator.userAgent}</p>
                </div>
              </details>

              {/* Botões de ação */}
              <div className="flex flex-col gap-3 pt-4">
                {error === 'temporarily_unavailable' || error === 'server_error' ? (
                  <Button 
                    onClick={handleRefresh}
                    className="w-full h-12"
                    size="lg"
                  >
                    <RefreshCw className="w-4 h-4 mr-2" />
                    Tentar Novamente
                  </Button>
                ) : null}
                
                <Button 
                  onClick={handleGoHome}
                  variant="outline"
                  className="w-full h-12"
                  size="lg"
                >
                  <Home className="w-4 h-4 mr-2" />
                  Voltar ao Início
                </Button>
              </div>
            </CardContent>
          </Card>

          {/* Footer */}
          <div className="text-center text-xs text-slate-500 dark:text-slate-400">
            <p>Se o problema persistir, entre em contato com o suporte técnico</p>
            <p className="mt-1">WMJ Identity Provider - OAuth 2.0 / OpenID Connect</p>
          </div>
        </div>
      </div>
    </>
  );
}