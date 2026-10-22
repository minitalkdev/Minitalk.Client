/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 서버프로그램과 소켓통신을 위한 함수를 정의한다.
 * 
 * @file /scripts/widget.socket.js
 * @license MIT License
 * @modified 2025. 2. 7.
 */
Minitalk.socket = {
	io:null,
	connection:null,
	connecting:false,
	connectionAttempt:0,
	reconnectTimer:null,
	connected:false,
	joined:false,
	reconnectable:true,
	pendingMessages:[],
	channel:null,
	permission:null,
	token:null,
	uuid:null,
	/**
	 * 미니톡 채팅서버에 접속한다.
	 *
	 * @param boolean reconnect 재접속시도여부
	 */
	connect:function() {
		/**
		 * 서버접속을 시도중이면 접속시도를 중단한다.
		 */
		if (Minitalk.socket.connecting === true || Minitalk.socket.connected === true) return;
		Minitalk.socket.connecting = true;
		var attempt = ++Minitalk.socket.connectionAttempt;
		clearTimeout(Minitalk.socket.reconnectTimer);
		
		Minitalk.socket.reconnectable = true;
		
		$.send(Minitalk.getProcessUrl("getServer"),{channel:Minitalk.channel},function(result) {
			if (attempt !== Minitalk.socket.connectionAttempt || Minitalk.socket.reconnectable !== true) return;
			if (result.success == true) {
				/**
				 * 이벤트를 발생시킨다.
				 */
				if (Minitalk.fireEvent("beforeConnect",[result.connection]) === false) {
					Minitalk.socket.connecting = false;
					return;
				}
				
				Minitalk.socket.channel = result.channel;
				Minitalk.ui.initChannel();
				Minitalk.ui.notify("connecting","action",Minitalk.getText("action/connecting"),false,false);
				Minitalk.socket.connecting = true;
				Minitalk.socket.io = io(result.connection.domain,{reconnection:false,path:"/minitalk",transports:["websocket"],secure:result.connection.domain.indexOf("https://") == 0});
				Minitalk.socket.connection = result.connection;
				
				/**
				 * 프로토콜 이벤트를 등록한다.
				 */
				for (var command in Minitalk.protocol) {
					(function(command, socket) {
					socket.on(command,function() {
						if (Minitalk.socket.io === socket && attempt === Minitalk.socket.connectionAttempt) Minitalk.protocol[command].apply(this,arguments);
					});
				})(command,Minitalk.socket.io);
				}
			} else {
				Minitalk.socket.connecting = false;
				Minitalk.ui.printError(result.error);
			}
		});
	},
	/**
	 * 소켓접속을 종료한다.
	 *
	 */
	disconnect:function() {
		Minitalk.socket.reconnectable = false;
		++Minitalk.socket.connectionAttempt;
		clearTimeout(Minitalk.socket.reconnectTimer);
		clearTimeout(Minitalk.ui.uploadReconnectTimer);
		Minitalk.ui.uploadReconnectTimer = null;
		Minitalk.ui.uploadReconnectStarted = null;
		Minitalk.socket.pendingMessages = [];
		var socket = Minitalk.socket.io;
		if (socket) socket.disconnect();
		Minitalk.socket.disconnected();
	},
	/**
	 * 미니톡 채팅서버에 재접속한다.
	 *
	 * @param int time 재접속할 시간
	 */
	reconnect:function(time) {
		/**
		 * 재접속이 허용되지 않았으면 접속시도를 중단한다.
		 */
		if (Minitalk.socket.reconnectable == false) return;
		
		clearTimeout(Minitalk.socket.reconnectTimer);
		if (time <= 0) {
			Minitalk.socket.connect();
		} else {
			Minitalk.ui.notify("connecting","action",Minitalk.getText("action/reconnecting").replace("{SECOND}",time),false,false);
			
			/**
			 * 동시에 서버재접속을 시도하지 않도록 1초 간격을 랜덤하게 조절한다.
			 */
			Minitalk.socket.reconnectTimer = setTimeout(Minitalk.socket.reconnect,900 + Math.ceil(Math.random() * 300 % 300),--time);
		}
	},
	/**
	 * 미니톡 서버접속이 종료되었을 경우
	 */
	disconnected:function() {
		/**
		 * 접속자수를 초기화한다.
		 */
		Minitalk.user.updateCount(0);
		
		/**
		 * 소켓변수를 초기화한다.
		 */
		if (Minitalk.socket.io) Minitalk.socket.io.removeAllListeners();
		Minitalk.socket.io = null;
		Minitalk.socket.connecting = false;
		Minitalk.socket.connected = false;
		Minitalk.socket.joined = false;
		
		/**
		 * 채팅위젯 UI를 비활성화한다.
		 */
		Minitalk.ui.disable();
		
		/**
		 * 이벤트를 발생시킨다.
		 */
		Minitalk.fireEvent("disconnect",[Minitalk.socket.reconnectable]);
	},
	/**
	 * 서버에 접속중인지 확인한다.
	 *
	 * @return boolean isConnected
	 */
	isConnected:function() {
		return Minitalk.socket.connecting !== true && Minitalk.socket.connected === true;
	},
	/**
	 * 채널에 참여중인지 확인한다.
	 *
	 * @return boolean isJoined
	 */
	isJoined:function() {
		return Minitalk.socket.isConnected() === true && Minitalk.socket.joined === true;
	},
	/**
	 * 권한정보를 가져온다.
	 *
	 * @param string name 변수명
	 * @return boolean hasPermission 권한여부
	 */
	getPermission:function(name) {
		if (Minitalk.socket.connected !== true) return false;
		return Minitalk.socket.permission !== undefined && Minitalk.socket.permission[name] !== undefined ? Minitalk.socket.permission[name] : false;
	},
	/**
	 * 접속코드를 전송한다.
	 */
	sendConnection:function() {
		/**
		 * 접속정보 객체를 생성한다.
		 */
		var join = {
			connection:Minitalk.socket.connection.connection,
			channel:Minitalk.socket.connection.channel,
			usercode:Minitalk.usercode ? Minitalk.usercode : null,
			authorization:Minitalk.storage("authorization"),
			box:Minitalk.box.connection
		};
		Minitalk.socket.send("join",join);
	},
	/**
	 * 데이터를 전송한다.
	 *
	 * @param string protocol 프로토콜
	 * @param object data 전송할 데이터
	 */
	send:function(protocol,data) {
		/**
		 * 접속 프로토콜을 제외한 나머지 프로토콜의 경우 접속상태에서만 전송한다.
		 */
		if (!Minitalk.socket.io || (protocol != "join" && Minitalk.socket.isConnected() === false)) {
			Minitalk.ui.printSystemMessage("error",Minitalk.getErrorText("SEND_ERROR"));
			return;
		}
		
		/**
		 * 특수프로토콜을 제외한 나머지 프로토콜을 전송하려고 하는 경우 전송권한이 있는지 확인한다.
		 */
		if ($.inArray(protocol,["join","logs","users","login","authorization","permission"]) === -1 && Minitalk.socket.getPermission("send") === false) {
			Minitalk.ui.printErrorCode(403);
			return;
		}
		
		Minitalk.socket.io.emit(protocol,data);
		return true;
	},
	/**
	 * 메시지를 전송한다.
	 *
	 * @param string type 메시지 유형 (message, file, ...)
	 * @param string message 전송할 메시지
	 * @param object data 메시지에 추가적으로 첨부할 데이터
	 * @param boolean isPrint 메시지를 출력할지 여부
	 */
	queueFileMessage:function(file) {
		if (Minitalk.socket.reconnectable !== true) return;
		Minitalk.socket.pendingMessages.push({type:"file",message:file.name,data:file,channel:Minitalk.channel,box:Minitalk.box.connection});
		Minitalk.socket.flushPendingMessages();
		if (!Minitalk.socket.isConnected() && !Minitalk.socket.connecting) Minitalk.socket.connect();
	},
	flushPendingMessages:function() {
		if (!Minitalk.socket.isJoined() || Minitalk.socket.reconnectable !== true) return;
		while (Minitalk.socket.pendingMessages.length > 0) {
			var next = Minitalk.socket.pendingMessages[0];
			if (next.channel !== Minitalk.channel || next.box !== Minitalk.box.connection) {
				Minitalk.socket.pendingMessages.shift();
				Minitalk.ui.printSystemMessage("error",Minitalk.getErrorText("SEND_ERROR"));
				continue;
			}
			if (Minitalk.socket.sendMessage(next.type,next.message,next.data) !== true) return;
			Minitalk.socket.pendingMessages.shift();
		}
	},
	sendMessage:function(type,message,data,isPrint) {
		if (Minitalk.socket.isJoined() !== true) return false;
		/**
		 * 메시지를 보낼 수 있는 권한이 있는지 확인한다.
		 */
		if (Minitalk.socket.getPermission("send") === false) {
			Minitalk.ui.printErrorCode(403);
			return;
		}
		
		var data = data !== undefined && typeof data == "object" ? data : null;
		var isPrint = isPrint === false ? false : true;
		var message = message ? $.trim(message) : "";
		
		/**
		 * 메시지의 고유 ID를 할당한다.
		 */
		var uuid = uuidv4();
		
		/**
		 * 폰트권한이 있고 폰트설정이 있다면 메시지 데이터에 포함하여 전송한다.
		 */
		if (type == "message" && Minitalk.socket.getPermission("font") == true) {
			if (Minitalk.fonts("bold") == true) message = "[B]" + message + "[/B]";
			if (Minitalk.fonts("italic") == true) message = "[I]" + message + "[/I]";
			if (Minitalk.fonts("underline") == true) message = "[U]" + message + "[/U]";
			if (Minitalk.fonts("color") !== null) message = "[COLOR=" + Minitalk.fonts("color") + "]" + message + "[/COLOR]";
		}
		
		/**
		 * 서버로 메시지를 전송한다.
		 */
		if (Minitalk.socket.send("message",{id:uuid,type:type,message:message,data:data,to:null}) !== true) return false;
		
		if (isPrint == true) {
			/**
			 * 메시지를 화면에 출력한다.
			 */
			Minitalk.ui.printMessage({id:uuid,type:type,message:Minitalk.ui.encodeMessage(message),data:data,uuid:Minitalk.socket.uuid,to:null,user:Minitalk.user.me});
		}
		
		Minitalk.ui.disable(true);
		return true;
	},
	/**
	 * 귓속말을 전송한다.
	 *
	 * @param string to 메시지를 받는사람
	 * @param string type 메시지 유형 (message, file, ...)
	 * @param string message 전송할 메시지
	 * @param object data 메시지에 추가적으로 첨부할 데이터
	 * @param boolean isPrint 메시지를 출력할지 여부
	 */
	sendWhisper:function(to,type,message,data,isPrint) {
		/**
		 * 메시지를 보낼 수 있는 권한이 있는지 확인한다.
		 */
		if (Minitalk.socket.getPermission("send") === false) {
			Minitalk.ui.printErrorCode(403);
			return;
		}
		
		var data = data !== undefined && typeof data == "object" ? data : null;
		var isPrint = isPrint === false ? false : true;
		var message = message ? $.trim(message) : "";
		
		/**
		 * 메시지의 고유 ID를 할당한다.
		 */
		var uuid = uuidv4();
		
		/**
		 * 폰트권한이 있고 폰트설정이 있다면 메시지 데이터에 포함하여 전송한다.
		 */
		if (type == "message" && Minitalk.socket.getPermission("font") == true) {
			if (Minitalk.fonts("bold") == true) message = "[B]" + message + "[/B]";
			if (Minitalk.fonts("italic") == true) message = "[I]" + message + "[/I]";
			if (Minitalk.fonts("underline") == true) message = "[U]" + message + "[/U]";
			if (Minitalk.fonts("color") !== null) message = "[COLOR=" + Minitalk.fonts("color") + "]" + message + "[/COLOR]";
		}
		
		/**
		 * 서버로 메시지를 전송한다.
		 */
		Minitalk.socket.send("message",{id:uuid,type:type,message:message,data:data,to:to});
		
		if (isPrint == true) {
			/**
			 * 메시지를 화면에 출력한다.
			 */
			Minitalk.ui.printMessage({id:uuid,type:type,message:Minitalk.ui.encodeMessage(message),data:data,uuid:Minitalk.socket.uuid,to:to,user:Minitalk.user.me});
		}
		
		Minitalk.ui.disable(true);
	},
	/**
	 * 메시지를 업데이트한다.
	 *
	 * @param string action 수정/삭제 여부
	 * @param object message 수정/삭제할 메시지객체
	 */
	updateMessage:function(action,message) {
		/**
		 * 메시지를 업데이트할 수 있는 권한이 있는지 확인한다.
		 */
		if (Minitalk.socket.getPermission("send") === false) {
			Minitalk.ui.printErrorCode(403);
			return false;
		}
		
		/**
		 * 메시지객체를 정리한다.
		 */
		message.action = action;
		
		if (message.to != null) {
			message.to = message.to.nickname;
		}
		
		/**
		 * 변경사항을 전송한다.
		 */
		Minitalk.socket.send("message",message);
		
		return true;
	},
	/**
	 * 사용자정의 프로토콜을 정의한다.
	 *
	 * @param string protocol 프로토콜명
	 * @param function handler 프로토콜 핸들러
	 */
	setProtocol:function(protocol,handler) {
		if (Minitalk.protocols[protocol] !== undefined) {
			Minitalk.ui.printError(Minitalk.getErrorText("DUPLICATED_PROTOCOL").replace("{PROTOCOL}",protocol));
			return;
		}
		
		Minitalk.protocols[protocol] = handler;
	},
	/**
	 * 사용자정의 프로토콜을 전송한다.
	 *
	 * @param string protocol 프로토콜명
	 * @param object data 전송할 데이터
	 * @param string to 전송할 대상닉네임 ( * 입력시 전체유저에게 전송, 기본값 전체유저)
	 */
	sendProtocol:function(protocol,data,to) {
		if (Minitalk.protocols[protocol] === undefined) {
			Minitalk.ui.printSystemMessage("error",Minitalk.getErrorText("UNDEFINED_PROTOCOL").replace("{PROTOCOL}",protocol));
			return;
		}
		
		var data = data ? data : null;
		var to = to ? to : "*";
		
		Minitalk.socket.send("protocol",{protocol:protocol,data:data,to:to});
	}
};