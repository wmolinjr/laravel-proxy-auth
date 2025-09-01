import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Shield, User, Mail, AlertCircle, Check } from 'lucide-react';

interface AuthorizeProps {
  client: {
    id: string;
    name: string;
    redirect_uri: string;
  };
  scopes: Array<{
    id: string;
    name: string;
    description: string;
  }>;
  user: {
    id: number;
    name: string;
    email: string;
  };
}

const scopeIcons = {
  openid: Shield,
  profile: User,
  email: Mail,
};

const scopeColors = {
  openid: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
  profile: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
  email: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
};

export default function Authorize({ client, scopes, user }: AuthorizeProps) {
  const { post, processing } = useForm();

  const handleApprove = (e: FormEvent) => {
    e.preventDefault();
    post('/oauth/authorize', {
      data: { approve: 'yes' },
      preserveScroll: true,
    });
  };

  const handleDeny = (e: FormEvent) => {
    e.preventDefault();
    post('/oauth/authorize', {
      data: { approve: 'no' },
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title={`Autorizar ${client.name}`} />
      
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800 p-4">
        <div className="w-full max-w-md space-y-6">
          {/* Header com logo/brand */}
          <div className="text-center">
            <div className="mx-auto w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mb-4">
              <Shield className="w-8 h-8 text-white" />
            </div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">
              WMJ Identity Provider
            </h1>
            <p className="text-slate-600 dark:text-slate-400">
              Sistema de Autenticação Centralizado
            </p>
          </div>

          <Card className="shadow-lg border-0">
            <CardHeader className="text-center pb-2">
              <div className="flex items-center justify-center gap-2 mb-2">
                <AlertCircle className="w-5 h-5 text-orange-500" />
                <CardTitle className="text-lg">Solicitação de Acesso</CardTitle>
              </div>
              <CardDescription className="text-base">
                <strong className="text-slate-900 dark:text-slate-100">{client.name}</strong> está solicitando permissão para acessar sua conta
              </CardDescription>
            </CardHeader>
            
            <CardContent className="space-y-6">
              {/* Informações do usuário */}
              <div className="bg-slate-50 dark:bg-slate-800 rounded-lg p-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                    <User className="w-5 h-5 text-white" />
                  </div>
                  <div>
                    <p className="font-medium text-slate-900 dark:text-slate-100">
                      {user.name}
                    </p>
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                      {user.email}
                    </p>
                  </div>
                </div>
              </div>

              <Separator />

              {/* Permissões solicitadas */}
              <div>
                <h3 className="font-semibold mb-4 flex items-center gap-2 text-slate-900 dark:text-slate-100">
                  <Check className="w-4 h-4" />
                  Permissões Solicitadas
                </h3>
                <div className="space-y-3">
                  {scopes.map((scope) => {
                    const Icon = scopeIcons[scope.id as keyof typeof scopeIcons] || Shield;
                    const colorClass = scopeColors[scope.id as keyof typeof scopeColors] || 'bg-gray-100 text-gray-800';
                    
                    return (
                      <div key={scope.id} className="flex items-center justify-between p-3 bg-white dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600">
                        <div className="flex items-center gap-3">
                          <Icon className="w-5 h-5 text-slate-600 dark:text-slate-400" />
                          <div>
                            <p className="font-medium text-slate-900 dark:text-slate-100">
                              {scope.description}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                              Escopo: {scope.name}
                            </p>
                          </div>
                        </div>
                        <Badge variant="secondary" className={colorClass}>
                          {scope.name}
                        </Badge>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Informações adicionais */}
              <div className="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-lg">
                <div className="flex items-start gap-3">
                  <AlertCircle className="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                      Ao autorizar você permite que:
                    </p>
                    <ul className="text-sm text-blue-800 dark:text-blue-200 mt-1 space-y-1">
                      <li>• {client.name} acesse as informações listadas acima</li>
                      <li>• A aplicação funcione em seu nome</li>
                      <li>• Seus dados sejam compartilhados conforme solicitado</li>
                    </ul>
                  </div>
                </div>
              </div>

              {/* Botões de ação */}
              <div className="flex flex-col gap-3 pt-2">
                <Button 
                  onClick={handleApprove} 
                  className="w-full h-12 bg-blue-600 hover:bg-blue-700 text-white"
                  disabled={processing}
                  size="lg"
                >
                  {processing ? 'Processando...' : 'Autorizar Acesso'}
                </Button>
                
                <Button 
                  onClick={handleDeny} 
                  variant="outline"
                  className="w-full h-12"
                  disabled={processing}
                  size="lg"
                >
                  Cancelar
                </Button>
              </div>

              {/* URL de redirecionamento */}
              <div className="text-center pt-4 border-t border-slate-200 dark:border-slate-700">
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  Você será redirecionado para:
                </p>
                <p className="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded mt-1 text-slate-700 dark:text-slate-300 break-all">
                  {client.redirect_uri}
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Footer */}
          <div className="text-center text-xs text-slate-500 dark:text-slate-400">
            <p>Protegido por WMJ Identity Provider</p>
            <p>OAuth 2.0 / OpenID Connect</p>
          </div>
        </div>
      </div>
    </>
  );
}