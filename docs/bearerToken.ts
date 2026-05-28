import { Request, Response, NextFunction } from 'express';
import * as jwt from 'jsonwebtoken';
import * as fs from 'fs';
import * as path from 'path';

interface McpTokenPayload {
    iss: string;
    aud: string | string[];
    sub: string;
    jti: string;
    iat: number;
    nbf: number;
    exp: number;
    scope: string;
}

declare global {
    namespace Express {
        interface Request {
            user?: McpTokenPayload;
        }
    }
}

const publicKey = fs.readFileSync(
    path.join(__dirname, '../storage/oauth/public.key'),
    'utf-8'
);

export function authenticateWithMcpToken(
    req: Request,
    res: Response,
    next: NextFunction
): void {
    const authHeader = req.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
        res.status(401).json({ message: 'Unauthenticated' });
        return;
    }

    const token = authHeader.substring(7);

    try {
        const decoded = jwt.verify(token, publicKey, {
            algorithms: ['RS256'],
            issuer: process.env.APP_URL,
            audience: process.env.MCP_SERVER_URL,
        }) as McpTokenPayload;

        req.user = decoded;

        next();
    } catch (error) {
        res.status(401).json({ message: 'Invalid token' });
    }
}
