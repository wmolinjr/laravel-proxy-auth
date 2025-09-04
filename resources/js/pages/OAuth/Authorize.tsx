import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Shield, User, Check, X } from 'lucide-react';

interface Scope {
    id: string;
    name: string;
    description: string;
}

interface Client {
    id: string;
    name: string;
    redirect_uri: string;
}

interface UserInfo {
    id: number;
    name: string;
    email: string;
}

interface Props {
    client: Client;
    scopes: Scope[];
    user: UserInfo;
    csrf_token: string;
}

export default function Authorize({ client, scopes, user, csrf_token }: Props) {

    const getScopeIcon = (scopeId: string) => {
        switch (scopeId) {
            case 'openid':
                return <Shield className="h-4 w-4" />;
            case 'profile':
                return <User className="h-4 w-4" />;
            case 'email':
                return <User className="h-4 w-4" />;
            default:
                return <Shield className="h-4 w-4" />;
        }
    };

    const getScopeDescription = (scope: Scope) => {
        switch (scope.id) {
            case 'openid':
                return 'Identificar você de forma segura';
            case 'profile':
                return 'Acessar seu nome e informações básicas do perfil';
            case 'email':
                return 'Acessar seu endereço de email';
            default:
                return scope.description || `Permissão para ${scope.name}`;
        }
    };

    return (
        <>
            <Head title={`Autorizar ${client.name}`} />
            
            <div className="min-h-screen bg-background flex items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="flex justify-center mb-4">
                            <Shield className="h-12 w-12 text-primary" />
                        </div>
                        <CardTitle className="text-xl">Autorização Necessária</CardTitle>
                        <CardDescription>
                            <strong>{client.name}</strong> está solicitando acesso à sua conta
                        </CardDescription>
                    </CardHeader>
                    
                    <CardContent className="space-y-6">
                        {/* User Info */}
                        <div className="bg-muted p-4 rounded-lg">
                            <div className="flex items-center gap-3">
                                <div className="bg-primary text-primary-foreground rounded-full w-10 h-10 flex items-center justify-center font-semibold">
                                    {user.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div className="font-medium">{user.name}</div>
                                    <div className="text-sm text-muted-foreground">{user.email}</div>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Requested Permissions */}
                        <div className="space-y-3">
                            <h4 className="font-medium flex items-center gap-2">
                                <Shield className="h-4 w-4" />
                                Permissões solicitadas:
                            </h4>
                            
                            <div className="space-y-2">
                                {scopes.map((scope) => (
                                    <div key={scope.id} className="flex items-start gap-3 p-3 bg-muted rounded-lg">
                                        <div className="text-primary mt-0.5">
                                            {getScopeIcon(scope.id)}
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{scope.name}</span>
                                                <Badge variant="secondary" className="text-xs">
                                                    {scope.id}
                                                </Badge>
                                            </div>
                                            <p className="text-sm text-muted-foreground mt-1">
                                                {getScopeDescription(scope)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <Separator />

                        {/* Client Info */}
                        <div className="text-sm text-muted-foreground space-y-1">
                            <div><strong>Aplicação:</strong> {client.name}</div>
                            <div><strong>Redirecionará para:</strong> {client.redirect_uri}</div>
                        </div>

                        {/* Actions */}
                        <div className="flex gap-3 pt-4">
                            <form method="POST" action="/oauth/authorize" className="flex-1">
                                <input type="hidden" name="_token" value={csrf_token} />
                                <input type="hidden" name="approve" value="no" />
                                <Button 
                                    type="submit"
                                    variant="outline" 
                                    className="w-full"
                                >
                                    <X className="h-4 w-4 mr-2" />
                                    Negar
                                </Button>
                            </form>
                            
                            <form method="POST" action="/oauth/authorize" className="flex-1">
                                <input type="hidden" name="_token" value={csrf_token} />
                                <input type="hidden" name="approve" value="yes" />
                                <Button 
                                    type="submit"
                                    className="w-full"
                                >
                                    <Check className="h-4 w-4 mr-2" />
                                    Autorizar
                                </Button>
                            </form>
                        </div>

                        {/* Security Notice */}
                        <div className="text-xs text-muted-foreground bg-muted p-3 rounded">
                            <strong>⚠️ Importante:</strong> Só autorize aplicações em que você confia. 
                            Esta aplicação terá acesso às informações listadas acima enquanto você 
                            estiver conectado.
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}