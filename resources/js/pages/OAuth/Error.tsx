import { Head } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertTriangle } from 'lucide-react';

interface Props {
    error: string;
    error_description: string;
    status: number;
}

export default function Error({ error, error_description, status }: Props) {
    const getErrorTitle = () => {
        switch (error) {
            case 'invalid_client':
                return 'Cliente OAuth Inválido';
            case 'invalid_request':
                return 'Requisição Inválida';
            case 'unauthorized_client':
                return 'Cliente Não Autorizado';
            case 'access_denied':
                return 'Acesso Negado';
            case 'unsupported_response_type':
                return 'Tipo de Resposta Não Suportado';
            case 'invalid_scope':
                return 'Escopo Inválido';
            case 'server_error':
                return 'Erro do Servidor';
            case 'temporarily_unavailable':
                return 'Temporariamente Indisponível';
            default:
                return 'Erro de Autorização OAuth';
        }
    };

    const getErrorMessage = () => {
        switch (error) {
            case 'invalid_client':
                return 'O cliente OAuth especificado não foi encontrado ou está inativo. Verifique se o client_id está correto.';
            case 'invalid_request':
                return 'A requisição de autorização está malformada ou contém parâmetros inválidos.';
            case 'unauthorized_client':
                return 'O cliente não está autorizado a solicitar um código de autorização usando este método.';
            case 'access_denied':
                return 'O usuário ou servidor de autorização negou a solicitação.';
            case 'unsupported_response_type':
                return 'O servidor de autorização não suporta a obtenção de um código de autorização usando este método.';
            case 'invalid_scope':
                return 'O escopo solicitado é inválido, desconhecido ou malformado.';
            case 'server_error':
                return 'O servidor de autorização encontrou uma condição inesperada que o impediu de atender à solicitação.';
            case 'temporarily_unavailable':
                return 'O servidor de autorização está temporariamente sobrecarregado ou em manutenção.';
            default:
                return error_description || 'Ocorreu um erro durante o processo de autorização OAuth.';
        }
    };

    return (
        <>
            <Head title={`Erro OAuth - ${getErrorTitle()}`} />
            
            <div className="min-h-screen bg-background flex items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="flex justify-center mb-4">
                            <AlertTriangle className="h-12 w-12 text-destructive" />
                        </div>
                        <CardTitle className="text-xl">{getErrorTitle()}</CardTitle>
                    </CardHeader>
                    
                    <CardContent className="space-y-4">
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>Erro {status}</AlertTitle>
                            <AlertDescription className="mt-2">
                                {getErrorMessage()}
                            </AlertDescription>
                        </Alert>

                        {error_description && error_description !== getErrorMessage() && (
                            <div className="text-sm text-muted-foreground bg-muted p-3 rounded">
                                <strong>Detalhes técnicos:</strong><br />
                                {error_description}
                            </div>
                        )}

                        <div className="text-sm text-muted-foreground bg-muted p-3 rounded">
                            <strong>Código do erro:</strong> {error}<br />
                            <strong>Status HTTP:</strong> {status}
                        </div>

                        <div className="flex gap-2 pt-4">
                            <Button 
                                variant="outline" 
                                className="flex-1"
                                onClick={() => window.history.back()}
                            >
                                Voltar
                            </Button>
                            <Button 
                                className="flex-1"
                                onClick={() => window.location.href = '/'}
                            >
                                Ir para Início
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}