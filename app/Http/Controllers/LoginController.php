<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class LoginController extends Controller
{

    public function login(Request $request){
        if(isset($_SESSION)){
            return redirect()->route('home', app()->getLocale());
        }else{
        //Validar Datos
        $msg = "";
        // $user = DB::select(
        //     "EXEC spAccesoApp :user, :password",
        //     [
        //         "user" => $request->usuario,
        //         "password" => $request->password,
        //     ]
        // );
        $query = "SELECT TOP 1 * FROM PRVusuarios WHERE usuario =  '$request->usuario' AND clave = '$request->password'";
        $consulta = DB::select($query);
        $user = $consulta[0];


        if($user->usuario != $request->usuario){
            $msg = "Usuario o Contraseña Incorrecta";
            return view('login')->with('msg', $msg);
        }
        if($user->clave != $request->password){
            $msg = "Contraseña Incorrecta";
            return view('login')->with('msg', $msg);
        }
        else{
            session_start();
            $_SESSION['usuario'] = $user;
            return redirect()->route('home', app()->getLocale())->with('usuario', $user);
        }
    }

        //$user[0]->Nombre
    }

    public function logout(Request $request){
        session_start();
        unset($_SESSION['usuario']);
        session_destroy();
        return redirect()->route('login', app()->getLocale());
    }

}
