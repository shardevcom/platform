export interface User {
  id: string
  name: string
  email: string
  roles?: string[]
}

export interface AuthResponse {
  access_token: string
  token_type: string
  user: User
}
